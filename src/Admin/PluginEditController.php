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
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginRequirements;
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
 * `site.settings`:
 *
 * - `PUT plugins/{vendor}/{name}` with `{"enabled": true|false}` saves
 *   the plugins turned off in `user/data/settings.json`
 *   (`plugins.disabled`), over `config/plugins.php`'s `disabled`, as
 *   activating a theme does (D-381). Turning one on is refused when
 *   `config/plugins.php`'s `enabled` list leaves it out (`409`) or its
 *   requirements aren't met (`422`, saying why). Answers `{"enabled",
 *   "started", "stopped", "refresh"}`: the other plugins that start or
 *   stop with it (the ones that require it), and that the admin should
 *   ask for `settings/refresh`, since providers run at boot.
 * - `DELETE plugins/{folder}` removes a plugin's folder from
 *   `user/plugins` and clears the plugin cache, answering `{"deleted"}`
 *   with where it was. One that's running can't be (`409`: turn it off
 *   first), nor one `config/plugins.php` turns on by name, since the
 *   site would fail without it. Composer plugins aren't folders in
 *   `user/plugins`, so they're never found here.
 */
final readonly class PluginEditController
{
	public function __construct(
		private Paths $paths,
		private Plugins $plugins,
		private PluginConfig $config,
		private SettingsFile $settings,
		private ContentVersion $version,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private Filesystem $filesystem
	) {}

	public function toggle(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to turn plugins on and off.', Status::Forbidden);
		}

		$enable = self::input($request)['enabled'] ?? null;

		if (! is_bool($enable)) {
			return self::error('Send "enabled": true or false.', Status::BadRequest);
		}

		try {
			$installed = self::keyed(PluginDiscovery::forPaths($this->paths)->discover());
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$plugin = $installed["{$vendor}/{$name}"] ?? null;

		if ($plugin === null) {
			return self::error(sprintf('No plugin named %s/%s is installed.', $vendor, $name), Status::NotFound);
		}

		$requirements = new PluginRequirements();
		$before       = array_keys(array_filter($installed, fn (PluginManifest $other): bool => $this->plugins->has($other->name)));

		if ($enable) {
			if (($locked = PluginsController::locked($this->config, $plugin->name)) !== null) {
				return self::error($locked, Status::Conflict);
			}

			$checked = $requirements->check($plugin, $installed, array_fill_keys($before, true));

			if (! PluginRequirements::met($checked)) {
				return self::error(sprintf('%s can\'t be turned on. %s', $plugin->label, PluginRequirements::reason($checked)), Status::UnprocessableContent);
			}
		}

		try {
			$saved = $this->settings->update(function (Settings $settings) use ($plugin, $enable): Settings {
				$disabled = $settings->has(Setting::Plugins) ? self::names($settings->get(Setting::Plugins)) : $this->config->disabled;
				$disabled = $enable
					? array_values(array_diff($disabled, [$plugin->name]))
					: [...$disabled, $plugin->name];

				return $settings->with([Setting::Plugins->value => $disabled]);
			});
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		// Which plugins run once it's saved, to say which others start or stop.
		$config  = new PluginConfig($this->config->enabled, self::names($saved->get(Setting::Plugins)));
		$after   = array_map(static fn (PluginManifest $other): string => $other->name, Plugins::enabled(array_values($installed), $config, $requirements)->all());
		$label   = static fn (string $other): string => $installed[$other]->label ?? $other;

		$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		$this->version->bump();

		return Response::json([
			'enabled' => $enable,
			'started' => array_map($label, array_values(array_diff($after, $before, [$plugin->name]))),
			'stopped' => array_map($label, array_values(array_diff($before, $after, [$plugin->name]))),
			'refresh' => true
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function delete(ServerRequestInterface $request, string $folder): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to delete plugins.', Status::Forbidden);
		}

		$path  = "{$this->paths->plugins}/{$folder}";
		$where = $this->paths->relative($path);

		try {
			$plugins = PluginDiscovery::forPaths($this->paths)->discover();
		} catch (ExtensionException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$plugin = array_find($plugins, static fn (PluginManifest $plugin): bool => $plugin->source === PluginSource::Local && $plugin->path === $path);

		if (str_starts_with($folder, '.') || ! is_dir($path) || $plugin === null) {
			return self::error(sprintf('There\'s no plugin in %s.', $where), Status::NotFound);
		}

		if ($this->plugins->has($plugin->name)) {
			return self::error(sprintf('%s is on. Turn it off before deleting it.', $plugin->label), Status::Conflict);
		}

		if (in_array($plugin->name, $this->config->enabled ?? [], true)) {
			return self::error(sprintf('config/plugins.php turns %s on by name. Take it out of that file\'s "enabled" list before deleting it.', $plugin->label), Status::Conflict);
		}

		try {
			$this->filesystem->removeDirectory($path);
		} catch (FilesystemException) {
			return self::error(sprintf('%s couldn\'t be deleted. Check that the web server may change it.', $where), Status::InternalServerError);
		}

		$this->bootstrap->clearCompiled(CompiledCache::Plugins);

		// The saved list forgets it, so the same name put back starts on.
		try {
			$this->settings->update(static fn (Settings $settings): Settings => $settings->has(Setting::Plugins)
				? $settings->with([Setting::Plugins->value => array_values(array_diff(self::names($settings->get(Setting::Plugins)), [$plugin->name]))])
				: $settings);
		} catch (InvalidSetting) {
			// The folder is gone either way; a name left in the list is harmless.
		}

		return Response::json(['deleted' => $where], headers: ['Cache-Control' => 'no-store']);
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

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteSettings);
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
