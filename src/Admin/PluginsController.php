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

use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginRequirements;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Plugin\Requirement;
use Blush\Settings\Setting;
use Blush\Settings\SettingsFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers `GET {path}/api/plugins` (D-308, D-378, D-385), for accounts
 * with `extensions.plugins.view` (D-389): every installed plugin, by label, with its
 * `name`, `label`, `namespace`, `version`, `description`, `authors`
 * (D-384's shape), `license`, `source` (`local` or `composer`), `path`
 * (from the site's root), and:
 *
 * - `folder`: its folder in `user/plugins`, or `null` for a Composer one.
 * - `enabled`: whether it's turned on (`config/plugins.php`, or the list
 *   the admin saved in `user/data/settings.json` over it), and `running`:
 *   whether it runs on this request, which an enabled plugin doesn't when
 *   its requirements aren't met.
 * - `requirements`: each of its `requires`, checked against the site
 *   (`{"name", "constraint", "kind", "met", "note", "label"}`); for one
 *   that's off, as if it were turned on. `blocked` says why one can't run
 *   (`null` when it can), and `requiredBy` names the plugins that require
 *   it.
 * - `locked`: why it can't be turned on here (`config/plugins.php`'s
 *   `enabled` list leaves it out), or `null`.
 * - `deletable`: a folder plugin that isn't running, and that
 *   `config/plugins.php` doesn't turn on by name.
 *
 * Also `saved` (the admin's list is in `settings.json`) and `config`
 * (whether `config/plugins.php` exists). What a plugin registers isn't
 * listed: it shows on the screens it belongs to, and a plugin that's off
 * registers nothing to list.
 */
final readonly class PluginsController
{
	public function __construct(
		private Paths $paths,
		private Plugins $plugins,
		private PluginConfig $config,
		private SettingsFile $settings,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::Plugin))) {
			return self::error('You aren\'t allowed to see the site\'s plugins.', Status::Forbidden);
		}

		try {
			$installed = self::keyed(PluginDiscovery::forPaths($this->paths)->discover());
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$running = [];
		$blocked = [];

		foreach ($installed as $name => $plugin) {
			if ($this->plugins->has($name)) {
				$running[$name] = true;
			} elseif ($this->config->isEnabled($name)) {
				$blocked[$name] = true;
			}
		}

		$requirements = new PluginRequirements();
		$plugins      = [];

		foreach ($installed as $name => $plugin) {
			$checked = $requirements->check($plugin, $installed, $running, $blocked);
			$folder  = $this->folder($plugin);

			$plugins[] = [
				'name'         => $plugin->name,
				'label'        => $plugin->label,
				'namespace'    => $plugin->namespace,
				'version'      => $plugin->version,
				'description'  => $plugin->description,
				'authors'      => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $plugin->authors),
				'license'      => $plugin->license,
				'source'       => $plugin->source->value,
				'path'         => $this->paths->relative($plugin->path),
				'folder'       => $folder,
				'enabled'      => $this->config->isEnabled($name),
				'running'      => isset($running[$name]),
				'requirements' => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), $checked),
				'blocked'      => PluginRequirements::met($checked) ? null : PluginRequirements::reason($checked),
				'requiredBy'   => array_keys(array_filter($installed, static fn (PluginManifest $other): bool => array_key_exists($name, $other->requires))),
				'locked'       => self::locked($this->config, $name),
				'deletable'    => $folder !== null && ! isset($running[$name]) && ! in_array($name, $this->config->enabled ?? [], true)
			];
		}

		usort($plugins, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']) ?: strcmp($a['name'], $b['name']));

		return Response::json([
			'plugins' => $plugins,
			'saved'   => $this->settings->read()->has(Setting::Plugins),
			'config'  => is_file("{$this->paths->config}/plugins.php")
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Why a plugin can't be turned on here, or `null`: `config/plugins.php`
	 * lists the only plugins it allows, and not this one.
	 */
	public static function locked(PluginConfig $config, string $name): ?string
	{
		return $config->enabled === null || in_array($name, $config->enabled, true)
			? null
			: 'config/plugins.php turns on only the plugins its "enabled" list names, and this isn\'t one of them.';
	}

	/**
	 * A plugin's folder in `user/plugins`, from the site's root, or `null`
	 * when it isn't one.
	 */
	private function folder(PluginManifest $plugin): ?string
	{
		return $plugin->source === PluginSource::Local && dirname($plugin->path) === $this->paths->plugins
			? $this->paths->relative($plugin->path)
			: null;
	}

	/**
	 * Keys manifests by name.
	 *
	 * @param  list<PluginManifest> $plugins
	 * @return array<string, PluginManifest>
	 */
	private static function keyed(array $plugins): array
	{
		$keyed = [];

		foreach ($plugins as $plugin) {
			$keyed[$plugin->name] = $plugin;
		}

		return $keyed;
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
