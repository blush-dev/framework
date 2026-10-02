<?php

/**
 * Admin plugins controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Action\AdminActionRegistry;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Component\ComponentRegistry;
use Blush\Console\CommandRegistry;
use Blush\Console\Input\Signature;
use Blush\Container\Container;
use Blush\Content\Type\ContentTypeSource;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeOrigin;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconRegistry;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;

/**
 * Answers `GET {path}/api/plugins` (D-308, D-378), for accounts with
 * `site.settings`: every installed plugin, the ones that are on first,
 * then by name, with its `name`, `label`, `namespace`, `version`,
 * `description`, `source` (`local` or `composer`), `path` (from the
 * site's root), `requires`, whether it's `enabled` (`config/plugins.php`
 * turns plugins off), and what it `adds`:
 *
 * - `types`: the content types its `ContentTypeSource`s define, each
 *   `{"name", "label", "overridden"}` (`overridden` when the site
 *   redefines it in `config/content.php`).
 * - `components` and `icons`: the namespaces it registers them under,
 *   its declared namespace, each with the components' full names or the
 *   icon namespace.
 * - `actions` and `commands`: the admin actions and console commands
 *   whose classes are its own.
 *
 * A class is a plugin's when it's under one of its PSR-4 prefixes or its
 * provider's namespace. A plugin that's off registers nothing, so it
 * adds nothing. Also: whether `config/plugins.php` exists (`config`).
 *
 * Plugins are installed and turned on and off by developers (D-039),
 * so the screen only shows them.
 */
final readonly class PluginsController
{
	public function __construct(
		private Paths $paths,
		private PluginConfig $config,
		private ContentTypes $types,
		private ComponentRegistry $components,
		private IconRegistry $icons,
		private AdminActionRegistry $actions,
		private Container $container,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return self::error('You aren\'t allowed to see the site\'s plugins.', Status::Forbidden);
		}

		try {
			$installed = PluginDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$enabled    = array_values(array_filter($installed, fn (PluginManifest $plugin): bool => $this->config->isEnabled($plugin->name)));
		$adds       = $this->adds($enabled);
		$plugins = [];

		foreach ($installed as $plugin) {
			$plugins[] = [
				'name'        => $plugin->name,
				'label'       => $plugin->label,
				'namespace'   => $plugin->namespace,
				'version'     => $plugin->version,
				'description' => $plugin->description,
				'source'      => $plugin->source->value,
				'path'        => $this->paths->relative($plugin->path),
				'requires'    => (object) $plugin->requires,
				'enabled'     => $this->config->isEnabled($plugin->name),
				'adds'        => $adds[$plugin->name] ?? self::nothing()
			];
		}

		// The ones that are on first, then by name.
		usort($plugins, static fn (array $a, array $b): int => [! $a['enabled'], $a['name']] <=> [! $b['enabled'], $b['name']]);

		return Response::json([
			'plugins' => $plugins,
			'config'  => is_file("{$this->paths->config}/plugins.php")
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * What each enabled plugin adds, by name.
	 *
	 * @param  list<PluginManifest> $plugins
	 * @return array<string, array<string, list<mixed>>>
	 */
	private function adds(array $plugins): array
	{
		$adds = array_fill_keys(array_map(static fn (PluginManifest $plugin): string => $plugin->name, $plugins), self::nothing());

		foreach ($this->container->taggedAbstracts(ContentTypeSource::TAG) as $class) {
			$owner = self::owner($class, $plugins);

			if ($owner === null) {
				continue;
			}

			try {
				$source = $this->container->get($class);
				$types  = $source instanceof ContentTypeSource ? $source->types() : [];

				foreach ($types as $type) {
					$adds[$owner]['types'][] = [
						'name'       => $type->name,
						'label'      => $type->labels->plural,
						'overridden' => $this->types->has($type->name) && $this->types->origin($type->name) !== TypeOrigin::Extension
					];
				}
			} catch (Throwable) {
				// A broken source shows up where types load; here it adds nothing.
			}
		}

		foreach ($this->components->all() as $name => $definition) {
			foreach (self::byNamespace($definition->name->namespace, $plugins) as $owner) {
				$adds[$owner]['components'][] = $name;
			}
		}

		foreach (array_keys($this->icons->all()) as $namespace) {
			foreach (self::byNamespace($namespace, $plugins) as $owner) {
				$adds[$owner]['icons'][] = $namespace;
			}
		}

		foreach ($this->actions->all() as $name => $class) {
			$owner = self::owner($class, $plugins);

			if ($owner !== null) {
				$adds[$owner]['actions'][] = $name;
			}
		}

		foreach ($this->container->taggedAbstracts(CommandRegistry::TAG) as $class) {
			$owner = self::owner($class, $plugins);

			if ($owner === null) {
				continue;
			}

			try {
				$signature = Signature::fromClass($class);
			} catch (Throwable) {
				continue;
			}

			if (! $signature->hidden) {
				$adds[$owner]['commands'][] = $signature->name;
			}
		}

		return $adds;
	}

	/**
	 * The plugin a class belongs to: the one with the longest PSR-4
	 * prefix or provider namespace the class is under, or `null`.
	 *
	 * @param list<PluginManifest> $plugins
	 */
	private static function owner(string $class, array $plugins): ?string
	{
		$class = ltrim($class, '\\');
		$best  = null;
		$depth = 0;

		foreach ($plugins as $plugin) {
			$provider = ltrim($plugin->providerClass(), '\\');
			$prefixes = [...array_keys($plugin->autoload), substr($provider, 0, (int) strrpos($provider, '\\') + 1)];

			foreach ($prefixes as $prefix) {
				$prefix = trim($prefix, '\\') . '\\';

				if ($prefix !== '\\' && str_starts_with($class, $prefix) && strlen($prefix) > $depth) {
					$best  = $plugin->name;
					$depth = strlen($prefix);
				}
			}
		}

		return $best;
	}

	/**
	 * The plugins a component or icon namespace belongs to: the one that
	 * declares it (D-378).
	 *
	 * @param  list<PluginManifest> $plugins
	 * @return list<string>
	 */
	private static function byNamespace(string $namespace, array $plugins): array
	{
		return array_values(array_map(
			static fn (PluginManifest $plugin): string => $plugin->name,
			array_filter($plugins, static fn (PluginManifest $plugin): bool => $plugin->namespace === $namespace)
		));
	}

	/**
	 * Adds nothing.
	 *
	 * @return array<string, list<mixed>>
	 */
	private static function nothing(): array
	{
		return ['types' => [], 'components' => [], 'icons' => [], 'actions' => [], 'commands' => []];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
