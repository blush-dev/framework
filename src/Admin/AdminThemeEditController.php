<?php

/**
 * Admin theme edit controller.
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
use Blush\Admin\Theme\AdminTheme;
use Blush\Admin\Theme\AdminThemeConfig;
use Blush\Admin\Theme\AdminThemes;
use Blush\Admin\Theme\AdminThemeSource;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionState;
use Blush\Extension\ExtensionText;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\Install\InstallException;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Requirements;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Changes the site's admin themes from the Admin Themes screens (D-827),
 * saved in the site's settings over `config/admin-themes.php`, as icon
 * packs' are (D-385, D-391):
 *
 * - `PUT admin-themes/{vendor}/{name}` (`{"enabled": bool}`, needs
 *   `extensions.admin-themes.activate`): turns a theme on or off,
 *   saving the list of every theme that's on. Neutral is always on, and
 *   the site's default can't be turned off; choose another first. One
 *   whose requirements aren't met can't be turned on (D-431).
 * - `PUT admin-themes/default` (`{"name"}`, the same capability): makes
 *   a theme that's offered the site's default, which anyone who hasn't
 *   chosen one sees, and the sign-in screen.
 * - `DELETE admin-themes/{vendor}/{name}` (`extensions.admin-themes.delete`):
 *   deletes a folder theme (or a broken one) from `extensions/`, but
 *   never the site's default. The saved list forgets it.
 *
 * An account that chose a theme that's turned off or deleted sees the
 * default; nothing about the account changes. Admin themes don't change
 * the site, so its cached pages stay.
 */
final readonly class AdminThemeEditController
{
	public function __construct(
		private Paths $paths,
		private AdminThemes $themes,
		private ExtensionState $extensions,
		private SettingsStore $settings,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private Filesystem $filesystem,
		private ExtensionInstaller $installer,
		private ExtensionText $text
	) {}

	public function toggle(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Activate)) {
			return self::error('You aren\'t allowed to turn admin themes on and off.', Status::Forbidden);
		}

		$enable = self::input($request)['enabled'] ?? null;

		if (! is_bool($enable)) {
			return self::error('Send "enabled": true or false.', Status::BadRequest);
		}

		$theme = $this->themes->find("{$vendor}/{$name}");

		if ($theme === null) {
			return self::error(sprintf('No admin theme named %s/%s is installed.', $vendor, $name), Status::NotFound);
		}

		$label = (string) $this->text->label($theme);

		if (! $enable && $theme->name === AdminThemes::BASE) {
			return self::error(sprintf('%s is the admin\'s own look, so it\'s always on.', $label), Status::UnprocessableContent);
		}

		if (! $enable && $this->isDefault($theme)) {
			return self::error(sprintf('%s is the site\'s default, so it can\'t be turned off. Choose another default first.', $label), Status::UnprocessableContent);
		}

		// One whose requirements aren't met can't be turned on (D-431).
		$checked = $this->extensions->check($theme);

		if ($enable && ! Requirements::met($checked)) {
			return self::error(sprintf('%s can\'t be turned on. %s', $label, Requirements::reason($checked)), Status::UnprocessableContent);
		}

		try {
			$saved = $this->settings->update(function (Settings $settings) use ($theme, $enable): Settings {
				$enabled = array_values(array_diff($settings->has(Setting::AdminThemes) ? self::names($settings->get(Setting::AdminThemes)) : array_keys($this->themes->on()), [$theme->name]));

				return $settings->with([Setting::AdminThemes->value => $enable ? [...$enabled, $theme->name] : $enabled]);
			});
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		// What else starts or stops with it, of every kind: a plugin that
		// requires it, say (D-431).
		$after   = $this->extensions->with(adminThemes: new AdminThemeConfig($this->themes->config->enabled, self::names($saved->get(Setting::AdminThemes)), $this->themes->config->default));
		$starts  = $after->runningNotIn($this->extensions, $theme->name);
		$stops   = $this->extensions->runningNotIn($after, $theme->name);
		$labelOf = fn (ExtensionManifest $other): string => (string) $this->text->label($other);
		$started = array_map($labelOf, $starts);
		$stopped = array_map($labelOf, $stops);

		// Only code changes the site: a plugin that started or stopped with it.
		$refresh = array_any([...$starts, ...$stops], static fn (ExtensionManifest $other): bool => $other->kind()->runsCode());

		if ($refresh) {
			$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		}

		return Response::json([
			'enabled' => $enable,
			'started' => $started,
			'stopped' => $stopped,
			'refresh' => $refresh
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function setDefault(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Activate)) {
			return self::error('You aren\'t allowed to choose the site\'s admin theme.', Status::Forbidden);
		}

		$name  = self::input($request)['name'] ?? null;
		$theme = is_string($name) ? $this->themes->find($name) : null;

		if ($theme === null) {
			return self::error('Send "name": an installed admin theme\'s name.', Status::BadRequest);
		}

		if (! $this->themes->isOffered($theme->name)) {
			return self::error(sprintf('%s isn\'t turned on, so it can\'t be the default. Turn it on first.', $this->text->label($theme)), Status::UnprocessableContent);
		}

		try {
			$this->settings->update(static fn (Settings $settings): Settings => $settings->with([Setting::AdminThemeDefault->value => $theme->name]));
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json(['default' => $theme->name], headers: ['Cache-Control' => 'no-store']);
	}

	public function delete(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Delete)) {
			return self::error('You aren\'t allowed to delete admin themes.', Status::Forbidden);
		}

		$path  = LocalExtensions::path($this->paths, "{$vendor}/{$name}");
		$where = $this->paths->relative($path);
		$theme = array_find($this->themes->all(), static fn (AdminTheme $theme): bool => $theme->source === AdminThemeSource::Local && $theme->path === $path);

		if (! is_dir($path) || ($theme === null && ! array_key_exists($where, $this->themes->invalid()))) {
			return self::error(sprintf('There\'s no admin theme in %s.', $where), Status::NotFound);
		}

		if ($theme !== null && $this->isDefault($theme)) {
			return self::error(sprintf('%s is the site\'s default, so it can\'t be deleted. Choose another default first.', $this->text->label($theme)), Status::UnprocessableContent);
		}

		try {
			$this->filesystem->removeDirectory($path);
		} catch (FilesystemException) {
			return self::error(sprintf('%s couldn\'t be deleted. Check that the web server may change it.', $where), Status::InternalServerError);
		}

		LocalExtensions::prune($this->paths, $path);
		$this->bootstrap->clearCompiled(CompiledCache::AdminThemes);

		// Its backup goes with it (D-393).
		try {
			$this->installer->discard($path);
		} catch (InstallException) {
			// The theme is gone either way; the backup is inert.
		}

		// The saved list forgets it, so the same name put back starts off.
		if ($theme !== null) {
			try {
				$this->settings->update(static fn (Settings $settings): Settings => $settings->has(Setting::AdminThemes)
					? $settings->with([Setting::AdminThemes->value => array_values(array_diff(self::names($settings->get(Setting::AdminThemes)), [$theme->name]))])
					: $settings);
			} catch (InvalidSetting) {
				// The folder is gone either way; a name left in the list is harmless.
			}
		}

		return Response::json(['deleted' => $where], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Whether a theme is the site's default, as anyone who hasn't chosen
	 * one sees it (Neutral when the default set can't be shown).
	 */
	private function isDefault(AdminTheme $theme): bool
	{
		return $this->themes->default()?->name === $theme->name;
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

		return $account instanceof Account && $this->permissions->can($account, $action->on(ExtensionKind::AdminTheme));
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
