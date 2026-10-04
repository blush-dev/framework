<?php

/**
 * Extension install controller.
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
use Psr\Http\Message\UploadedFileInterface;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionKind;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\Install\ExtensionPackage;
use Blush\Extension\Install\InstallClash;
use Blush\Extension\Install\InstallException;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Installs an extension from a `.zip` of its folder (D-392):
 * `POST themes`, `POST plugins`, and `POST icon-packs`, with the archive
 * as the multipart field `file`, and `replace` set to `1` to replace an
 * installed one with its name. Installing needs the kind's `install`
 * capability, and replacing its `update` (D-389).
 *
 * Answers `201` with the extension `installed` (`{"name", "label",
 * "version", "folder", "abandoned"}`, `abandoned` being `false`, `true`,
 * or the package to use instead, D-433), the version it `replaced` (or `null`), where
 * the old folder was kept (`backup`), and whether the admin should ask for
 * `settings/refresh` (a running plugin or the active theme was replaced).
 * One with an installed one's name, without `replace`, is a `409` with
 * the `clash`: the `installed` one and the `incoming` one, each in the
 * same shape. Anything else that stops it is a `422`
 * saying why; an archive of another kind also names it (`kind`). Nothing
 * is written in either case. ExtensionInstaller says what's checked.
 */
final readonly class ExtensionInstallController
{
	public function __construct(
		private ExtensionInstaller $installer,
		private Permissions $permissions,
		private Paths $paths,
		private Bootstrap $bootstrap,
		private ContentVersion $version,
		private InstalledExtensions $extensions
	) {}

	public function theme(ServerRequestInterface $request): ResponseInterface
	{
		return $this->install($request, ExtensionKind::Theme);
	}

	public function plugin(ServerRequestInterface $request): ResponseInterface
	{
		return $this->install($request, ExtensionKind::Plugin);
	}

	public function iconPack(ServerRequestInterface $request): ResponseInterface
	{
		return $this->install($request, ExtensionKind::IconPack);
	}

	/**
	 * What a kind's screen shows before anything is chosen (its list's
	 * `upload`): the largest archive taken (`limit`, in bytes), and why
	 * nothing can be installed (`problem`), or `null`.
	 *
	 * @return array{limit: int, problem: ?string}
	 */
	public static function upload(ExtensionInstaller $installer, ExtensionKind $kind): array
	{
		return ['limit' => self::limit(), 'problem' => $installer->problem($kind)];
	}

	/**
	 * The version a folder extension's backup holds, as `{"version"}`, or
	 * `null` when it has none (D-393).
	 *
	 * @return ?array{version: string}
	 */
	public static function backup(ExtensionInstaller $installer, ExtensionKind $kind, ?string $path, string $name): ?array
	{
		$older = $path === null ? null : $installer->backupOf($kind, $path, $name);

		return $older === null ? null : ['version' => $older->version];
	}

	/**
	 * The largest archive taken: Blush's limit, or PHP's when it's lower.
	 */
	public static function limit(): int
	{
		return min(ExtensionInstaller::MAX_UPLOAD, MediaUploadController::limit() ?? ExtensionInstaller::MAX_UPLOAD);
	}

	private function install(ServerRequestInterface $request, ExtensionKind $kind): ResponseInterface
	{
		$body    = $request->getParsedBody();
		$replace = is_array($body) && ($body['replace'] ?? null) === '1';
		$action  = $replace ? ExtensionAction::Update : ExtensionAction::Install;
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, $action->on($kind))) {
			return self::error(sprintf('You aren\'t allowed to %s %ss.', $replace ? 'update' : 'install', $kind->label()), Status::Forbidden);
		}

		$upload = $request->getUploadedFiles()['file'] ?? null;

		if (! $upload instanceof UploadedFileInterface) {
			return self::error(sprintf('No file arrived. It may be larger than the site allows (up to %d MB).', intdiv(self::limit(), 1024 * 1024)), Status::BadRequest);
		}

		$name = basename(str_replace('\\', '/', (string) $upload->getClientFilename())) ?: 'The file';

		if ($upload->getError() !== UPLOAD_ERR_OK || ($upload->getSize() ?? 0) > self::limit()) {
			return self::error(sprintf('%s is larger than the site allows (up to %d MB).', $name, intdiv(self::limit(), 1024 * 1024)), Status::UnprocessableContent);
		}

		if (! str_ends_with(strtolower($name), '.zip')) {
			return self::error(sprintf('%s isn\'t a .zip file.', $name), Status::UnprocessableContent);
		}

		$file = sprintf('%s/install-%s.zip', $this->paths->cache, bin2hex(random_bytes(6)));

		try {
			if (! is_dir($this->paths->cache)) {
				@mkdir($this->paths->cache, 0775, true);
			}

			$upload->moveTo($file);
			$result = $this->installer->install($kind, $file, $name, $replace);
		} catch (InstallClash $clash) {
			return Response::json([
				'error' => $clash->getMessage(),
				'clash' => ['installed' => self::describe($this->paths, $clash->installed), 'incoming' => self::describe($this->paths, $clash->incoming, $clash->installed->path)]
			], Status::Conflict, ['Cache-Control' => 'no-store']);
		} catch (InstallException $error) {
			return Response::json(['error' => $error->getMessage(), 'kind' => $error->kind?->value], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		} catch (Throwable) {
			return self::error(sprintf('%s couldn\'t be saved to the server.', $name), Status::InternalServerError);
		} finally {
			@unlink($file);
		}

		$this->bootstrap->clearCompiled(match ($kind) {
			ExtensionKind::Plugin   => CompiledCache::Plugins,
			ExtensionKind::Theme    => CompiledCache::Themes,
			ExtensionKind::IconPack => CompiledCache::IconPacks
		});

		// Replacing what runs changes the site at once.
		$live = $result->replaced !== null && $this->extensions->live($kind, $result->package->name);

		if ($live) {
			$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
			$this->version->bump();
		}

		return Response::json([
			'installed' => self::describe($this->paths, $result->package),
			'replaced'  => $result->replaced?->version,
			'backup'    => $result->backup,
			'refresh'   => $live && $kind !== ExtensionKind::IconPack
		], Status::Created, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes an extension, in its folder (or the one it would take).
	 *
	 * @return array{name: string, label: string, version: string, folder: string, abandoned: bool|string}
	 */
	public static function describe(Paths $paths, ExtensionPackage $package, ?string $path = null): array
	{
		return [
			'name'    => $package->name,
			'label'   => $package->label,
			'version' => $package->version,
			'folder'    => $paths->relative($path ?? $package->path),
			'abandoned' => $package->abandoned
		];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
