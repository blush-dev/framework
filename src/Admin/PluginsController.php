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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginRequirements;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Plugin\Requirement;

/**
 * Answers `GET {path}/api/plugins` (D-308, D-378, D-385), for accounts
 * with `extensions.plugins.view` (D-389): every installed plugin, by label, with its
 * `name`, `label`, `namespace`, `version`, `description`, `authors`
 * (D-384's shape), `license`, `source` (`local` or `composer`), `path`
 * (from the site's root), and:
 *
 * - `folder`: its folder in `extensions/`, or `null` for a Composer one.
 * - `enabled`: whether it's turned on (`config/plugins.php`, or the list
 *   the admin saved in `user/data/settings.json` over it), and `running`:
 *   whether it runs on this request, which an enabled plugin doesn't when
 *   its requirements aren't met.
 * - `requirements`: each of its `require`, checked against the site
 *   (`{"name", "constraint", "kind", "met", "note", "label"}`); for one
 *   that's off, as if it were turned on. `blocked` says why one can't run
 *   (`null` when it can), and `requiredBy` names the plugins that require
 *   it.
 * - `deletable`: a folder plugin that isn't running, and that
 *   `config/plugins.php` doesn't turn on by name.
 *
 * Also `invalid`: the broken plugins (D-394), each with `where` (a
 * Composer package's name, or its folder), `reason`, `name` (`null` when
 * its manifest doesn't say), `enabled` (config turns it on, though it
 * can't run), and `deletable` (a folder in `extensions/` config doesn't
 * turn on by name); `saved` (the admin's list is in `settings.json`); and `config`
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
		private Permissions $permissions,
		private ExtensionInstaller $installer
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::Plugin))) {
			return self::error('You aren\'t allowed to see the site\'s plugins.', Status::Forbidden);
		}

		try {
			$discovered = PluginDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$installed = $discovered->keyed();

		$running = [];
		$blocked = [];

		foreach ($installed as $name => $plugin) {
			if ($this->plugins->has($name)) {
				$running[$name] = true;
			} elseif ($this->config->isEnabled($plugin)) {
				$blocked[$name] = true;
			}
		}

		$requirements = new PluginRequirements();
		$plugins      = [];
		$saved        = $this->config->saved !== null;

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
				'enabled'      => $this->config->isEnabled($plugin),
				'running'      => isset($running[$name]),
				'requirements' => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), $checked),
				'blocked'      => PluginRequirements::met($checked) ? null : PluginRequirements::reason($checked),
				'requiredBy'   => array_keys(array_filter($installed, static fn (PluginManifest $other): bool => array_key_exists($name, $other->require))),
				'deletable'    => $folder !== null && ! isset($running[$name]) && ! self::namedByConfig($this->config, $name),
				'backup'       => ExtensionInstallController::backup($this->installer, ExtensionKind::Plugin, $folder === null ? null : $plugin->path, $name)
			];
		}

		usort($plugins, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']) ?: strcmp($a['name'], $b['name']));

		$invalid = [];

		foreach ($discovered->broken as $plugin) {
			$invalid[] = [
				'where'     => $plugin->where,
				'reason'    => $plugin->reason,
				'name'      => $plugin->name === '' ? null : $plugin->name,
				'enabled'   => $plugin->name !== '' && $this->config->turnsOn($plugin->name, $plugin->source),
				'deletable' => $plugin->source === PluginSource::Local && LocalExtensions::nameAt($this->paths, $plugin->where) !== null && ($plugin->name === '' || ! self::namedByConfig($this->config, $plugin->name))
			];
		}

		return Response::json([
			'plugins' => $plugins,
			'invalid' => $invalid,
			'saved'   => $saved,
			'config'  => is_file("{$this->paths->config}/plugins.php"),
			'upload'  => ExtensionInstallController::upload($this->installer, ExtensionKind::Plugin)
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Whether `config/plugins.php` turns a plugin on by name: its list is
	 * the one in use (the admin hasn't saved one over it) and names it.
	 * The site would fail without it, so it can't be deleted.
	 */
	public static function namedByConfig(PluginConfig $config, string $name): bool
	{
		return $config->saved === null && in_array($name, $config->enabled, true);
	}

	/**
	 * A plugin's folder in `extensions/`, from the site's root, or `null`
	 * when it isn't one.
	 */
	private function folder(PluginManifest $plugin): ?string
	{
		return $plugin->source === PluginSource::Local && LocalExtensions::contains($this->paths, $plugin->path)
			? $this->paths->relative($plugin->path)
			: null;
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
