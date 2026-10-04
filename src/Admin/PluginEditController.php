<?php

/**
 * Admin plugin edit controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionState;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\Install\InstallException;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Requirements;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Plugin\BrokenPlugin;
use Blush\Plugin\DiscoveredPlugins;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Turns plugins on and off, and deletes them (D-385), for accounts with
 * `extensions.plugins.activate` to turn them on and off and
 * `extensions.plugins.delete` to delete them (D-389):
 *
 * - `PUT plugins/{vendor}/{name}` with `{"enabled": true|false}` saves
 *   every plugin that's on in `user/data/settings.json`
 *   (`plugins.enabled`), Composer's included, over `config/plugins.php`
 *   (D-391), as activating a theme does (D-381). The first save starts
 *   from what's on by default, so only the plugin switched changes.
 *   Turning one on is refused when its requirements aren't met, or it's
 *   broken (`422`, saying why; D-394). Answers `{"enabled",
 *   "started", "stopped", "refresh"}`: the other plugins that start or
 *   stop with it (the ones that require it), and that the admin should
 *   ask for `settings/refresh`, since providers run at boot.
 * - `DELETE plugins/{folder}` removes a plugin's folder from
 *   `extensions/` and clears the plugin cache, answering `{"deleted"}`
 *   with where it was, a broken one's included (D-394). One that's
 *   running can't be (`409`: turn it off first), nor one `config/plugins.php` turns on by name, since the
 *   site would fail without it. Composer plugins aren't folders in
 *   `extensions/`, so they're never found here.
 */
final readonly class PluginEditController
{
	public function __construct(
		private Paths $paths,
		private Plugins $plugins,
		private PluginConfig $config,
		private ExtensionState $extensions,
		private SettingsFile $settings,
		private ContentVersion $version,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private Filesystem $filesystem,
		private ExtensionInstaller $installer
	) {}

	public function toggle(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Activate)) {
			return self::error('You aren\'t allowed to turn plugins on and off.', Status::Forbidden);
		}

		$enable = self::input($request)['enabled'] ?? null;

		if (! is_bool($enable)) {
			return self::error('Send "enabled": true or false.', Status::BadRequest);
		}

		try {
			$discovered = PluginDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$installed = $discovered->keyed();
		$target    = "{$vendor}/{$name}";
		$plugin    = $installed[$target] ?? null;
		$broken    = array_find($discovered->broken, static fn (BrokenPlugin $broken): bool => $broken->name === $target);

		if ($plugin === null && $broken === null) {
			return self::error(sprintf('No plugin named %s is installed.', $target), Status::NotFound);
		}

		$before = $this->extensions->with(discovered: [$discovered->manifests, $discovered->broken]);

		if ($enable) {
			// A broken one can only be turned off (D-394).
			if ($plugin === null) {
				return self::error(sprintf('%s can\'t be turned on. %s', $target, $broken->reason), Status::UnprocessableContent);
			}

			$checked = $before->check($plugin);

			if (! Requirements::met($checked)) {
				return self::error(sprintf('%s can\'t be turned on. %s', $plugin->label, Requirements::reason($checked)), Status::UnprocessableContent);
			}
		}

		try {
			$saved = $this->settings->update(function (Settings $settings) use ($target, $enable, $discovered): Settings {
				$enabled = array_values(array_diff($settings->has(Setting::Plugins) ? self::names($settings->get(Setting::Plugins)) : $this->on($discovered), [$target]));

				return $settings->with([Setting::Plugins->value => $enable ? [...$enabled, $target] : $enabled]);
			});
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		// What runs once it's saved, of every kind, to say what else starts
		// or stops (D-431).
		$after = $before->with(new PluginConfig(saved: self::names($saved->get(Setting::Plugins))));
		$label = static fn (ExtensionManifest $other): string => $other->label;

		$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		$this->version->bump();

		return Response::json([
			'enabled' => $enable,
			'started' => array_map($label, $after->runningNotIn($before, $target)),
			'stopped' => array_map($label, $before->runningNotIn($after, $target)),
			'refresh' => true
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function delete(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Delete)) {
			return self::error('You aren\'t allowed to delete plugins.', Status::Forbidden);
		}

		$path  = LocalExtensions::path($this->paths, "{$vendor}/{$name}");
		$where = $this->paths->relative($path);

		try {
			$discovered = PluginDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$plugin = array_find($discovered->manifests, static fn (PluginManifest $plugin): bool => $plugin->source === PluginSource::Local && $plugin->path === $path);
		$broken = array_find($discovered->broken, static fn (BrokenPlugin $broken): bool => $broken->source === PluginSource::Local && $broken->where === $where);

		if (! is_dir($path) || ($plugin === null && $broken === null)) {
			return self::error(sprintf('There\'s no plugin in %s.', $where), Status::NotFound);
		}

		// A broken one never runs (D-394), but config may still name it.
		$named = $plugin->name ?? $broken->name ?? '';
		$label = $plugin->label ?? $where;

		if ($this->plugins->has($named)) {
			return self::error(sprintf('%s is on. Turn it off before deleting it.', $label), Status::Conflict);
		}

		if ($named !== '' && PluginsController::namedByConfig($this->config, $named)) {
			return self::error(sprintf('config/plugins.php turns %s on by name. Take it out of that file\'s "enabled" list before deleting it.', $label), Status::Conflict);
		}

		try {
			$this->filesystem->removeDirectory($path);
		} catch (FilesystemException) {
			return self::error(sprintf('%s couldn\'t be deleted. Check that the web server may change it.', $where), Status::InternalServerError);
		}

		LocalExtensions::prune($this->paths, $path);
		$this->bootstrap->clearCompiled(CompiledCache::Plugins);

		// Its backup goes with it (D-393).
		try {
			$this->installer->discard($path);
		} catch (InstallException) {
			// The extension is gone either way; the backup is inert.
		}

		// The saved list forgets it, so the same name put back starts off.
		try {
			$this->settings->update(static fn (Settings $settings): Settings => $settings->has(Setting::Plugins)
				? $settings->with([Setting::Plugins->value => array_values(array_diff(self::names($settings->get(Setting::Plugins)), [$named]))])
				: $settings);
		} catch (InvalidSetting) {
			// The folder is gone either way; a name left in the list is harmless.
		}

		return Response::json(['deleted' => $where], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The plugins on before the admin saves its first list (Composer's,
	 * and the local ones config names), so saving one changes nothing
	 * but the plugin switched (D-391). A broken one config turns on is
	 * kept on, so it runs once it's fixed (D-394).
	 *
	 * @return list<string>
	 */
	private function on(DiscoveredPlugins $discovered): array
	{
		return [
			...array_keys(array_filter($discovered->keyed(), $this->config->isEnabled(...))),
			...array_values(array_map(
				static fn (BrokenPlugin $plugin): string => $plugin->name,
				array_filter($discovered->broken, fn (BrokenPlugin $plugin): bool => $plugin->name !== '' && $this->config->turnsOn($plugin->name, $plugin->source))
			))
		];
	}

	/**
	 * A saved list of names.
	 *
	 * @return list<string>
	 */
	private static function names(mixed $value): array
	{
		return array_values(array_filter(is_array($value) ? $value : [], is_string(...)));
	}

	private function allowed(ServerRequestInterface $request, ExtensionAction $action): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, $action->on(ExtensionKind::Plugin));
	}

	/**
	 * The request's JSON object, or an empty array.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
