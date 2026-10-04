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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionKind;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\Install\InstallException;
use Blush\Extension\LocalExtensions;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSource;

/**
 * Deletes a theme's folder from `extensions/` (D-381), for accounts with
 * `extensions.themes.delete` (D-389): `DELETE themes/{folder}`, answering `{"deleted"}`
 * with where it was.
 *
 * Only a folder in `extensions/` that holds a theme, or a broken one, is
 * deleted. The active theme, and any theme it falls back to, can't be:
 * that's a `409` naming the theme to activate first. The default theme
 * and Composer themes aren't folders in `extensions/`, so they're never
 * found here (Composer removes its own). A theme that fell back to the
 * one deleted keeps naming it, so it can't be activated until it's
 * pointed at one that's installed.
 *
 * The theme cache is cleared, so the next request finds the themes
 * again.
 */
final readonly class ThemeEditController
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private Paths $paths,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private Filesystem $filesystem,
		private ExtensionInstaller $installer
	) {}

	public function delete(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ExtensionAction::Delete->on(ExtensionKind::Theme))) {
			return self::error('You aren\'t allowed to delete themes.', Status::Forbidden);
		}

		$path  = LocalExtensions::path($this->paths, "{$vendor}/{$name}");
		$where = $this->paths->relative($path);
		$theme = array_find($this->themes->all(), fn (ThemeManifest $theme): bool => $theme->source === ThemeSource::Local && $theme->path === $path);

		if (! is_dir($path) || ($theme === null && ! array_key_exists($where, $this->themes->invalid()))) {
			return self::error(sprintf('There\'s no theme in %s.', $where), Status::NotFound);
		}

		if ($theme !== null && ($refusal = $this->inUse($theme)) !== null) {
			return self::error($refusal, Status::Conflict);
		}

		try {
			$this->filesystem->removeDirectory($path);
		} catch (FilesystemException) {
			return self::error(sprintf('%s couldn\'t be deleted. Check that the web server may change it.', $where), Status::InternalServerError);
		}

		LocalExtensions::prune($this->paths, $path);
		$this->bootstrap->clearCompiled(CompiledCache::Themes);

		// Its backup goes with it (D-393).
		try {
			$this->installer->discard($path);
		} catch (InstallException) {
			// The extension is gone either way; the backup is inert.
		}

		return Response::json(['deleted' => $where], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Why a theme can't be deleted, when the site uses it: it's the active
	 * theme, or the active theme falls back to it.
	 */
	private function inUse(ThemeManifest $theme): ?string
	{
		if ($theme->name === $this->config->active) {
			return sprintf('%s is the active theme. Activate another theme before deleting it.', $theme->label);
		}

		try {
			$chain = $this->themes->chain($this->config->active);
		} catch (ThemeException) {
			return null;
		}

		if (! in_array($theme->name, $chain->names(), true)) {
			return null;
		}

		$active = $this->themes->find($this->config->active)->label ?? $this->config->active;

		return sprintf('The active theme, %s, falls back to %s. Activate another theme before deleting it.', $active, $theme->label);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
