<?php

/**
 * Admin icon pack edit controller.
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
use Blush\Cache\ContentVersion;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionKind;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconConfig;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;
use Blush\Icon\IconPackSource;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns icon packs on and off, and deletes them (D-385), for accounts
 * with `extensions.icon-packs.activate` to turn them on and off and
 * `extensions.icon-packs.delete` to delete them (D-389):
 *
 * - `PUT icon-packs/{vendor}/{name}` with `{"enabled": true|false}`
 *   saves the packs turned off in `user/data/settings.json`
 *   (`icons.disabled`), over `config/icons.php`'s `disabled`. A pack
 *   that's off adds no icons, so anywhere one is used shows nothing.
 *   Answers `{"enabled"}`.
 * - `DELETE icon-packs/{folder}` removes a pack's folder from
 *   `user/icons`, or a broken pack's, clears the icon pack cache, and
 *   answers `{"deleted"}` with where it was. Composer packs aren't
 *   folders in `user/icons`, so they're never found here.
 *
 * Either moves the content version on, so cached pages go.
 */
final readonly class IconPackEditController
{
	public function __construct(
		private Paths $paths,
		private IconPacks $packs,
		private IconConfig $config,
		private SettingsFile $settings,
		private ContentVersion $version,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private Filesystem $filesystem
	) {}

	public function toggle(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Activate)) {
			return self::error('You aren\'t allowed to turn icon packs on and off.', Status::Forbidden);
		}

		$enable = self::input($request)['enabled'] ?? null;

		if (! is_bool($enable)) {
			return self::error('Send "enabled": true or false.', Status::BadRequest);
		}

		$pack = $this->packs->find("{$vendor}/{$name}");

		if ($pack === null) {
			return self::error(sprintf('No icon pack named %s/%s is installed.', $vendor, $name), Status::NotFound);
		}

		try {
			$this->settings->update(function (Settings $settings) use ($pack, $enable): Settings {
				$disabled = $settings->has(Setting::IconPacks) ? self::names($settings->get(Setting::IconPacks)) : $this->config->disabled;
				$disabled = $enable
					? array_values(array_diff($disabled, [$pack->name]))
					: [...$disabled, $pack->name];

				return $settings->with([Setting::IconPacks->value => $disabled]);
			});
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$this->version->bump();

		return Response::json(['enabled' => $enable], headers: ['Cache-Control' => 'no-store']);
	}

	public function delete(ServerRequestInterface $request, string $folder): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Delete)) {
			return self::error('You aren\'t allowed to delete icon packs.', Status::Forbidden);
		}

		$path  = "{$this->paths->icons}/{$folder}";
		$where = $this->paths->relative($path);
		$pack  = array_find($this->packs->all(), static fn (IconPack $pack): bool => $pack->source === IconPackSource::Local && $pack->path === $path);

		if (str_starts_with($folder, '.') || ! is_dir($path) || ($pack === null && ! array_key_exists($where, $this->packs->invalid()))) {
			return self::error(sprintf('There\'s no icon pack in %s.', $where), Status::NotFound);
		}

		try {
			$this->filesystem->removeDirectory($path);
		} catch (FilesystemException) {
			return self::error(sprintf('%s couldn\'t be deleted. Check that the web server may change it.', $where), Status::InternalServerError);
		}

		$this->bootstrap->clearCompiled(CompiledCache::IconPacks);
		$this->version->bump();

		// The saved list forgets it, so the same name put back starts on.
		if ($pack !== null) {
			try {
				$this->settings->update(static fn (Settings $settings): Settings => $settings->has(Setting::IconPacks)
					? $settings->with([Setting::IconPacks->value => array_values(array_diff(self::names($settings->get(Setting::IconPacks)), [$pack->name]))])
					: $settings);
			} catch (InvalidSetting) {
				// The folder is gone either way; a name left in the list is harmless.
			}
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

	private function allowed(ServerRequestInterface $request, ExtensionAction $action): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, $action->on(ExtensionKind::IconPack));
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
