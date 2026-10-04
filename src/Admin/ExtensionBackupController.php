<?php

/**
 * Extension backup controller.
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
use Blush\Cache\ContentVersion;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionState;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\Install\InstallException;
use Blush\Extension\ManifestFile;
use Blush\Extension\Requirements;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconPack;
use Blush\Plugin\LocalPluginFinder;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;

/**
 * Rolls a folder extension back to the version replacing it kept, and
 * discards that backup (D-393), for each kind:
 *
 * - `POST {themes|plugins|icon-packs}/{vendor}/{name}/rollback` swaps the
 *   backup in, keeping the version it replaces as the backup, so rolling
 *   back again undoes it. It needs the kind's `update` capability. The
 *   earlier version must fit the site: a plugin's requirements met, and
 *   a theme's or pack's in use (D-431), and, for a theme in the active
 *   chain, the theme it falls back to installed; otherwise it's a `422`
 *   saying why. Answers `{"rolledBack",
 *   "from", "refresh"}`: the extension as it is now, the version it was,
 *   and whether the admin should ask for `settings/refresh` (it runs).
 * - `DELETE {themes|plugins|icon-packs}/{vendor}/{name}/backup` removes
 *   the backup. It needs the kind's `delete` capability.
 *
 * Deleting an extension deletes its backup too, so one is never left
 * behind.
 */
final readonly class ExtensionBackupController
{
	public function __construct(
		private ExtensionInstaller $installer,
		private InstalledExtensions $extensions,
		private Permissions $permissions,
		private Paths $paths,
		private Bootstrap $bootstrap,
		private ContentVersion $version,
		private Themes $themes,
		private ExtensionState $state
	) {}

	public function rollbackTheme(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->rollback($request, ExtensionKind::Theme, "{$vendor}/{$name}");
	}

	public function rollbackPlugin(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->rollback($request, ExtensionKind::Plugin, "{$vendor}/{$name}");
	}

	public function rollbackIconPack(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->rollback($request, ExtensionKind::IconPack, "{$vendor}/{$name}");
	}

	public function discardTheme(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->discard($request, ExtensionKind::Theme, "{$vendor}/{$name}");
	}

	public function discardPlugin(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->discard($request, ExtensionKind::Plugin, "{$vendor}/{$name}");
	}

	public function discardIconPack(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		return $this->discard($request, ExtensionKind::IconPack, "{$vendor}/{$name}");
	}

	private function rollback(ServerRequestInterface $request, ExtensionKind $kind, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Update, $kind)) {
			return self::error(sprintf('You aren\'t allowed to roll %ss back.', $kind->label()), Status::Forbidden);
		}

		$folder = $this->extensions->folder($kind, $name);
		$older  = $folder === null ? null : $this->installer->backupOf($kind, $folder, $name);

		if ($folder === null || $older === null) {
			return self::error(sprintf('There\'s no earlier version of %s to roll back to.', $name), Status::NotFound);
		}

		$live   = $this->extensions->live($kind, $name);
		$misfit = $this->misfit($kind, $name, $this->installer->backupPath($folder), $live);

		if ($misfit !== null) {
			return self::error(sprintf('%s %s can\'t be rolled back to: %s', $older->label, $older->version, $misfit), Status::UnprocessableContent);
		}

		try {
			$result = $this->installer->rollback($kind, $name);
		} catch (InstallException $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->bootstrap->clearCompiled(match ($kind) {
			ExtensionKind::Plugin   => CompiledCache::Plugins,
			ExtensionKind::Theme    => CompiledCache::Themes,
			ExtensionKind::IconPack => CompiledCache::IconPacks
		});

		if ($live) {
			$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
			$this->version->bump();
		}

		return Response::json([
			'rolledBack' => ExtensionInstallController::describe($this->paths, $result->package),
			'from'       => $result->replaced?->version,
			'refresh'    => $live && $kind !== ExtensionKind::IconPack
		], headers: ['Cache-Control' => 'no-store']);
	}

	private function discard(ServerRequestInterface $request, ExtensionKind $kind, string $name): ResponseInterface
	{
		if (! $this->allowed($request, ExtensionAction::Delete, $kind)) {
			return self::error(sprintf('You aren\'t allowed to delete %ss.', $kind->label()), Status::Forbidden);
		}

		$folder = $this->extensions->folder($kind, $name);

		if ($folder === null || ! is_dir($this->installer->backupPath($folder))) {
			return self::error(sprintf('%s has no earlier version kept.', $name), Status::NotFound);
		}

		try {
			$this->installer->discard($folder);
		} catch (InstallException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json(['discarded' => true], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Why the earlier version wouldn't fit the site, or `null`: one whose
	 * requirements aren't met (a plugin always; a theme or pack in use,
	 * D-431), or a theme in use whose parent isn't installed.
	 */
	private function misfit(ExtensionKind $kind, string $name, string $backup, bool $live): ?string
	{
		try {
			$older = match ($kind) {
				ExtensionKind::Plugin   => LocalPluginFinder::manifest($backup),
				ExtensionKind::Theme    => ThemeManifest::fromArray($backup, ManifestFile::load($backup, ExtensionKind::Theme)),
				ExtensionKind::IconPack => IconPack::fromArray($backup, ManifestFile::load($backup, ExtensionKind::IconPack))
			};

			if ($kind === ExtensionKind::Plugin || $live) {
				$checked = $this->state->check($older);

				if (! Requirements::met($checked)) {
					return Requirements::reason($checked);
				}
			}

			if ($older instanceof ThemeManifest && $live && $older->parent !== null && ! $this->themes->has($older->parent)) {
				return sprintf('it falls back to %s, which isn\'t installed.', $older->parent);
			}
		} catch (ExtensionException | ThemeException $error) {
			return $error->getMessage();
		}

		return null;
	}

	private function allowed(ServerRequestInterface $request, ExtensionAction $action, ExtensionKind $kind): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, $action->on($kind));
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
