<?php

/**
 * Admin extensions controller.
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
use Blush\Extension\ExtensionConfig;
use Blush\Extension\ExtensionDiscovery;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionManifest;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconRegistry;

/**
 * Answers `GET {path}/api/extensions` (D-308), for accounts with
 * `site.settings`: every installed extension, the ones that are on
 * first, then by name, with its
 * `name`, `version`, `description`, `source` (`local` or `composer`),
 * `path` (from the site's root), `requires`, whether it's `enabled`
 * (`config/extensions.php` turns extensions off), and what it `adds`:
 *
 * - `types`: the content types its `ContentTypeSource`s define, each
 *   `{"name", "label", "overridden"}` (`overridden` when the site
 *   redefines it in `config/content.php`).
 * - `components` and `icons`: the namespaces it registers them under,
 *   its vendor (or its own name, for a bare slug), each with the
 *   components' full names or the icon namespace.
 * - `actions` and `commands`: the admin actions and console commands
 *   whose classes are its own.
 *
 * A class is an extension's when it's under one of its PSR-4 prefixes
 * or its provider's namespace. An extension that's off registers
 * nothing, so it adds nothing. Also: whether `config/extensions.php`
 * exists (`config`).
 *
 * Extensions are installed and turned on and off by developers (D-039),
 * so the screen only shows them.
 */
final readonly class ExtensionsController
{
	public function __construct(
		private Paths $paths,
		private ExtensionConfig $config,
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
			return self::error('You aren\'t allowed to see the site\'s extensions.', Status::Forbidden);
		}

		try {
			$installed = ExtensionDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$enabled    = array_values(array_filter($installed, fn (ExtensionManifest $extension): bool => $this->config->isEnabled($extension->name)));
		$adds       = $this->adds($enabled);
		$extensions = [];

		foreach ($installed as $extension) {
			$extensions[] = [
				'name'        => $extension->name,
				'version'     => $extension->version,
				'description' => $extension->description,
				'source'      => $extension->source->value,
				'path'        => $this->paths->relative($extension->path),
				'requires'    => (object) $extension->requires,
				'enabled'     => $this->config->isEnabled($extension->name),
				'adds'        => $adds[$extension->name] ?? self::nothing()
			];
		}

		// The ones that are on first, then by name.
		usort($extensions, static fn (array $a, array $b): int => [! $a['enabled'], $a['name']] <=> [! $b['enabled'], $b['name']]);

		return Response::json([
			'extensions' => $extensions,
			'config'     => is_file("{$this->paths->config}/extensions.php")
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * What each enabled extension adds, by name.
	 *
	 * @param  list<ExtensionManifest> $extensions
	 * @return array<string, array<string, list<mixed>>>
	 */
	private function adds(array $extensions): array
	{
		$adds = array_fill_keys(array_map(static fn (ExtensionManifest $extension): string => $extension->name, $extensions), self::nothing());

		foreach ($this->container->taggedAbstracts(ContentTypeSource::TAG) as $class) {
			$owner = self::owner($class, $extensions);

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
			foreach (self::byNamespace($definition->name->namespace, $extensions) as $owner) {
				$adds[$owner]['components'][] = $name;
			}
		}

		foreach (array_keys($this->icons->all()) as $namespace) {
			foreach (self::byNamespace($namespace, $extensions) as $owner) {
				$adds[$owner]['icons'][] = $namespace;
			}
		}

		foreach ($this->actions->all() as $name => $class) {
			$owner = self::owner($class, $extensions);

			if ($owner !== null) {
				$adds[$owner]['actions'][] = $name;
			}
		}

		foreach ($this->container->taggedAbstracts(CommandRegistry::TAG) as $class) {
			$owner = self::owner($class, $extensions);

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
	 * The extension a class belongs to: the one with the longest PSR-4
	 * prefix or provider namespace the class is under, or `null`.
	 *
	 * @param list<ExtensionManifest> $extensions
	 */
	private static function owner(string $class, array $extensions): ?string
	{
		$class = ltrim($class, '\\');
		$best  = null;
		$depth = 0;

		foreach ($extensions as $extension) {
			$provider = ltrim($extension->providerClass(), '\\');
			$prefixes = [...array_keys($extension->autoload), substr($provider, 0, (int) strrpos($provider, '\\') + 1)];

			foreach ($prefixes as $prefix) {
				$prefix = trim($prefix, '\\') . '\\';

				if ($prefix !== '\\' && str_starts_with($class, $prefix) && strlen($prefix) > $depth) {
					$best  = $extension->name;
					$depth = strlen($prefix);
				}
			}
		}

		return $best;
	}

	/**
	 * The extensions a component or icon namespace belongs to: the one
	 * named it, or every one under that vendor.
	 *
	 * @param  list<ExtensionManifest> $extensions
	 * @return list<string>
	 */
	private static function byNamespace(string $namespace, array $extensions): array
	{
		return array_values(array_map(
			static fn (ExtensionManifest $extension): string => $extension->name,
			array_filter($extensions, static fn (ExtensionManifest $extension): bool => $extension->name === $namespace || str_starts_with($extension->name, "{$namespace}/"))
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
