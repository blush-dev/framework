<?php

/**
 * Extension installer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;
use Blush\Admin\Theme\AdminTheme;
use Blush\Admin\Theme\AdminThemeDiscovery;
use Blush\Admin\Theme\AdminThemeSource;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ExtensionText;
use Blush\Extension\LocalExtensions;
use Blush\Extension\ManifestFile;
use Blush\Icon\IconPack;
use Blush\Icon\IconPackDiscovery;
use Blush\Icon\IconPackSource;
use Blush\Plugin\LocalPluginFinder;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginSource;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Theme\ThemeDiscovery;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSource;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Installs an extension from a `.zip` of its folder into
 * `extensions/{vendor}/{name}` (D-388, D-392, D-418), or replaces one with
 * the same name.
 *
 * The archive is checked before anything is unpacked
 * (`ExtensionArchive`), then unpacked into a hidden folder in
 * `extensions/` (`extensions/.install-…`, which discovery skips), and
 * read as discovery would read it, its name from its manifest (or its
 * `composer.json`); the zip's own folder name doesn't matter. It's
 * refused, and the hidden folder removed, when:
 *
 * - it holds another kind of extension, more than one, or none (its
 *   kind is its manifest file, or its `composer.json`'s `type`; D-432);
 * - an extension of another kind has its name;
 * - its manifest doesn't pass, or its namespace is reserved or another
 *   installed extension's;
 * - Composer installed one with its name (Composer updates it);
 * - a kind that runs code has a PHP file that doesn't parse (checked
 *   without running it).
 *
 * A new one is renamed into `extensions/{vendor}/{name}`, which mustn't
 * exist yet. One with an installed one's name is a clash
 * (`InstallClash`) unless replacing is asked for; then the installed
 * folder is swapped for the new one (not a git checkout, which git
 * updates), and the old folder kept in `storage/backups/{vendor}/{name}`
 * until the next replace. Either way nothing is turned on: a new plugin
 * or pack is off until it's named (D-390), and a new theme inactive.
 *
 * A backup lasts as long as its extension (D-393): rolling back swaps it
 * in, keeping the version it replaces as the backup, so a rollback can be
 * undone; it can be discarded; and deleting the extension deletes it.
 */
final readonly class ExtensionInstaller
{
	/**
	 * The largest archive taken, in bytes, whatever PHP allows.
	 */
	public const int MAX_UPLOAD = 25 * 1024 * 1024;

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private ExtensionText $text,
		private Messages $messages
	) {}

	/**
	 * Why nothing of a kind can be installed here, or `null`.
	 */
	public function problem(ExtensionKind $kind): ?Text
	{
		if (! class_exists(ZipArchive::class)) {
			return $this->messages->t('extension.extension_installer.no_zip', 'This server\'s PHP can\'t read .zip files (it has no zip extension).');
		}

		$folder   = $this->paths->extensions;
		$writable = is_dir($folder) ? is_writable($folder) : is_writable(dirname($folder));

		return $writable ? null : $this->messages->t('extension.extension_installer.not_writable', 'The web server can\'t write to {folder}.', folder: $this->paths->relative($folder));
	}

	/**
	 * Installs an extension of a kind from an archive.
	 *
	 * @param  string $file    The archive.
	 * @param  string $name    Its name, for messages.
	 * @param  bool   $replace Whether to replace an installed one with its name.
	 * @throws InstallException When it isn't installed.
	 * @throws InstallClash     When one with its name is installed and replacing wasn't asked for.
	 */
	public function install(ExtensionKind $kind, string $file, string $name, bool $replace = false): InstallResult
	{
		$problem = $this->problem($kind);

		if ($problem !== null) {
			throw new InstallException((string) $problem);
		}

		$folder = $this->paths->extensions;

		if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.folder_not_made', '{folder} couldn\'t be made.', folder: $this->paths->relative($folder)));
		}

		$archive = ExtensionArchive::open($file, $name, $this->messages);
		$staging = sprintf('%s/.install-%s', $folder, bin2hex(random_bytes(6)));

		try {
			if (! @mkdir($staging, 0775)) {
				throw new InstallException((string) $this->messages->t('extension.extension_installer.not_unpacked', '{name} couldn\'t be unpacked into {folder}.', name: $name, folder: $this->paths->relative($folder)));
			}

			$archive->extractTo($staging);

			$this->expect($staging, $kind, $name);

			$incoming = $this->read($kind, $staging, $name);
			$existing = $this->check($incoming, $name);

			if ($existing !== null && ! $replace) {
				throw new InstallClash($existing, $incoming, $this->messages);
			}

			return $existing === null
				? $this->place($incoming, $folder)
				: $this->swap($existing, $incoming, $folder);
		} catch (Throwable $error) {
			$this->remove($staging);

			throw $error;
		} finally {
			$archive->close();
		}
	}

	/**
	 * Where an installed extension's backup is kept, from its folder
	 * (`extensions/{vendor}/{name}` keeps it in
	 * `storage/backups/{vendor}/{name}`).
	 */
	public function backupPath(string $path): string
	{
		return sprintf('%s/backups/%s/%s', $this->paths->storage, basename(dirname($path)), basename($path));
	}

	/**
	 * The backup of an installed extension, read as discovery would, or
	 * `null` when there's none (or it can't be read, or it's another
	 * extension's).
	 */
	public function backupOf(ExtensionKind $kind, string $path, string $name): ?ExtensionPackage
	{
		$backup = $this->backupPath($path);

		if (! is_dir($backup)) {
			return null;
		}

		try {
			$package = $this->read($kind, $backup, $name);
		} catch (InstallException) {
			return null;
		}

		return $package->name === $name ? $package : null;
	}

	/**
	 * Swaps an installed extension for its backup, which keeps the version
	 * it replaces, so rolling back again undoes it. The backup is checked
	 * as an archive would be.
	 *
	 * @throws InstallException When it can't be rolled back.
	 */
	public function rollback(ExtensionKind $kind, string $name): InstallResult
	{
		$existing = array_find($this->installed(), static fn (ExtensionPackage $package): bool => $package->kind === $kind && $package->name === $name && $package->local);

		if ($existing === null) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.not_installed', 'No {kind, select, Plugin {plugin} Theme {theme} IconPack {icon pack} other {admin theme}} named {name} is installed in {folder}.', kind: $kind->name, name: $name, folder: $this->paths->relative($this->paths->extensions)));
		}

		$backup = $this->backupPath($existing->path);
		$older  = $this->backupOf($kind, $existing->path, $name);

		if ($older === null) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.no_backup', 'There\'s no earlier version of {label} to roll back to.', label: $existing->label));
		}

		if (file_exists("{$existing->path}/.git")) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.git_rollback', '{folder} is a git checkout, so git changes its version, not a rollback.', folder: $this->paths->relative($existing->path)));
		}

		$this->check($older, $existing->label);

		$aside = sprintf('%s/.rollback-%s', $this->paths->extensions, bin2hex(random_bytes(6)));

		if (! @rename($existing->path, $aside)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.rollback_aside', '{folder} couldn\'t be moved aside to roll it back.', folder: $this->paths->relative($existing->path)));
		}

		if (! @rename($backup, $existing->path)) {
			@rename($aside, $existing->path);

			throw new InstallException((string) $this->messages->t('extension.extension_installer.not_rolled_back', '{label} couldn\'t be rolled back.', label: $existing->label));
		}

		if (! @rename($aside, $backup)) {
			$this->remove($aside);

			return new InstallResult(self::at($older, $existing->path), $existing);
		}

		return new InstallResult(self::at($older, $existing->path), $existing, $this->paths->relative($backup));
	}

	/**
	 * Removes an installed extension's backup, if it has one: discarding
	 * it, or once the extension is deleted.
	 *
	 * @throws InstallException When it can't be removed.
	 */
	public function discard(string $path): void
	{
		$backup = $this->backupPath($path);

		try {
			$this->filesystem->removeDirectory($backup);
		} catch (FilesystemException $error) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.backup_not_removed', 'The backup of {folder} couldn\'t be removed.', folder: $this->paths->relative($path)), previous: $error);
		}

		// Its vendor's folder goes once it's empty.
		if (is_dir(dirname($backup)) && (scandir(dirname($backup)) ?: []) === ['.', '..']) {
			@rmdir(dirname($backup));
		}
	}

	/**
	 * Checks that the unpacked folder is the kind of extension expected,
	 * by its manifest file or its `composer.json`'s `type` (D-432), and
	 * only that kind.
	 *
	 * @throws InstallException
	 */
	private function expect(string $folder, ExtensionKind $kind, string $name): void
	{
		$claims = LocalExtensions::claims($folder);

		if (count($claims) > 1) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.several_kinds', '{name} holds {claims}, but an extension is one kind.', name: $name, claims: LocalExtensions::describe($claims, $this->messages)));
		}

		if (array_key_exists($kind->value, $claims)) {
			return;
		}

		if ($claims !== []) {
			$other = ExtensionKind::from((string) array_key_first($claims));

			throw new InstallException((string) $this->messages->t('extension.extension_installer.wrong_kind', '{name} is {other, select, Plugin {a plugin} Theme {a theme} IconPack {an icon pack} other {an admin theme}}, not {kind, select, Plugin {a plugin} Theme {a theme} IconPack {an icon pack} other {an admin theme}}.', name: $name, other: $other->name, kind: $kind->name), $other);
		}

		throw new InstallException((string) $this->messages->t('extension.extension_installer.no_manifest', '{name} has no {manifest}.json in it, and no composer.json of type "{type}", so it isn\'t {kind, select, Plugin {a plugin} Theme {a theme} IconPack {an icon pack} other {an admin theme}}.', name: $name, manifest: $kind->manifest(), type: $kind->packageType(), kind: $kind->name));
	}

	/**
	 * Reads the unpacked extension as discovery would.
	 *
	 * @throws InstallException
	 */
	private function read(ExtensionKind $kind, string $folder, string $name): ExtensionPackage
	{
		try {
			return match ($kind) {
				ExtensionKind::Plugin     => $this->plugin($folder),
				ExtensionKind::Theme      => $this->theme($folder),
				ExtensionKind::IconPack   => $this->pack($folder),
				ExtensionKind::AdminTheme => $this->adminTheme($folder)
			};
		} catch (ExtensionException | ThemeException $error) {
			$reason = str_replace($folder, $kind->manifest(), $error->getMessage());

			throw new InstallException((string) $this->messages->t('extension.extension_installer.invalid', '{name} can\'t be installed: {reason}', name: $name, reason: $reason), previous: $error);
		}
	}

	/**
	 * @throws ExtensionException
	 */
	private function plugin(string $folder): ExtensionPackage
	{
		$plugin = LocalPluginFinder::manifest($folder, $this->messages);

		return new ExtensionPackage(ExtensionKind::Plugin, $plugin->name, (string) $this->text->label($plugin), $plugin->namespace, $plugin->version, $folder, true, $plugin->abandoned, $plugin->suggest);
	}

	/**
	 * @throws ThemeException
	 */
	private function theme(string $folder): ExtensionPackage
	{
		$theme = ThemeManifest::fromArray($folder, ManifestFile::load($folder, ExtensionKind::Theme, $this->messages), messages: $this->messages);

		if ($theme->name === Themes::DEFAULT) {
			throw new ThemeException((string) $this->messages->t('extension.extension_installer.default_theme_name', '"{name}" is the framework default theme\'s name.', name: Themes::DEFAULT));
		}

		return new ExtensionPackage(ExtensionKind::Theme, $theme->name, (string) $this->text->label($theme), $theme->namespace, $theme->version, $folder, true, $theme->abandoned, $theme->suggest);
	}

	/**
	 * @throws ExtensionException
	 */
	private function pack(string $folder): ExtensionPackage
	{
		$pack = IconPack::fromArray($folder, ManifestFile::load($folder, ExtensionKind::IconPack, $this->messages), messages: $this->messages);

		return new ExtensionPackage(ExtensionKind::IconPack, $pack->name, (string) $this->text->label($pack), $pack->namespace, $pack->version, $folder, true, $pack->abandoned, $pack->suggest);
	}

	/**
	 * Reads an admin theme, which has no namespace (D-827) and may not
	 * take a framework admin theme's name.
	 *
	 * @throws ExtensionException
	 */
	private function adminTheme(string $folder): ExtensionPackage
	{
		$theme = AdminTheme::fromArray($folder, ManifestFile::load($folder, ExtensionKind::AdminTheme, $this->messages));

		if (new AdminThemeDiscovery($this->paths)->discover()->find($theme->name)?->source === AdminThemeSource::Framework) {
			throw new ExtensionException((string) $this->messages->t('extension.extension_installer.admin_theme_name', '"{name}" is a framework admin theme\'s name.', name: $theme->name));
		}

		return new ExtensionPackage(ExtensionKind::AdminTheme, $theme->name, (string) $this->text->label($theme), '', $theme->version, $folder, true, $theme->abandoned, $theme->suggest);
	}

	/**
	 * Checks the unpacked extension against the site, and returns the
	 * installed one with its name, if any.
	 *
	 * @throws InstallException
	 */
	private function check(ExtensionPackage $incoming, string $name): ?ExtensionPackage
	{
		if (ExtensionNamespace::isReserved($incoming->namespace)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.reserved_namespace', '{name} can\'t be installed: its namespace, "{namespace}", is reserved.', name: $name, namespace: $incoming->namespace));
		}

		$existing = null;

		foreach ($this->installed() as $package) {
			if ($package->name === $incoming->name && $package->kind !== $incoming->kind) {
				throw new InstallException((string) $this->messages->t('extension.extension_installer.installed_as', '{name} can\'t be installed: {installed} is installed as {kind, select, Plugin {a plugin} Theme {a theme} IconPack {an icon pack} other {an admin theme}}.', name: $name, installed: $package->name, kind: $package->kind->name));
			}

			if ($package->name === $incoming->name) {
				if (! $package->local) {
					throw new InstallException((string) $this->messages->t('extension.extension_installer.composer', '{name} is installed with Composer, so Composer updates it.', name: $package->name));
				}

				$existing = $package;
			} elseif ($incoming->namespace !== '' && $package->namespace === $incoming->namespace) {
				throw new InstallException((string) $this->messages->t('extension.extension_installer.namespace_taken', '{name} can\'t be installed: its namespace, "{namespace}", is the {kind, select, Plugin {plugin} Theme {theme} IconPack {icon pack} other {admin theme}} {installed}\'s.', name: $name, namespace: $incoming->namespace, kind: $package->kind->name, installed: $package->name));
			}
		}

		if ($incoming->kind->runsCode()) {
			$this->lint($incoming->path, $name);
		}

		return $existing;
	}

	/**
	 * Checks that every PHP file parses, without running any of them.
	 *
	 * @throws InstallException
	 */
	private function lint(string $folder, string $name): void
	{
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS));

		foreach ($files as $file) {
			if (! $file instanceof SplFileInfo || ! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
				continue;
			}

			try {
				token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
			} catch (ParseError $error) {
				throw new InstallException((string) $this->messages->t(
					'extension.extension_installer.php_error',
					'{name} can\'t be installed: {file} has a PHP error on line {line} ({error}).',
					name: $name,
					file: substr($file->getPathname(), strlen($folder) + 1),
					line: $error->getLine(),
					error: $error->getMessage()
				), previous: $error);
			}
		}
	}

	/**
	 * Every extension installed, of every kind.
	 *
	 * @return list<ExtensionPackage>
	 * @throws InstallException When the plugins can't be read.
	 */
	private function installed(): array
	{
		try {
			$plugins = PluginDiscovery::forPaths($this->paths, $this->messages)->discover()->manifests;
		} catch (ExtensionException $error) {
			throw new InstallException($error->getMessage(), previous: $error);
		}

		$packages = [];

		foreach ($plugins as $plugin) {
			$packages[] = new ExtensionPackage(ExtensionKind::Plugin, $plugin->name, (string) $this->text->label($plugin), $plugin->namespace, $plugin->version, $plugin->path, $plugin->source === PluginSource::Local && LocalExtensions::contains($this->paths, $plugin->path));
		}

		foreach (new ThemeDiscovery($this->paths, $this->messages)->discover()->all() as $theme) {
			$packages[] = new ExtensionPackage(ExtensionKind::Theme, $theme->name, (string) $this->text->label($theme), $theme->namespace, $theme->version, $theme->path, $theme->source === ThemeSource::Local && LocalExtensions::contains($this->paths, $theme->path));
		}

		foreach (new IconPackDiscovery($this->paths, $this->messages)->discover()->all() as $pack) {
			$packages[] = new ExtensionPackage(ExtensionKind::IconPack, $pack->name, (string) $this->text->label($pack), $pack->namespace, $pack->version, $pack->path, $pack->source === IconPackSource::Local && LocalExtensions::contains($this->paths, $pack->path));
		}

		foreach (new AdminThemeDiscovery($this->paths)->discover()->all() as $adminTheme) {
			$packages[] = new ExtensionPackage(ExtensionKind::AdminTheme, $adminTheme->name, (string) $this->text->label($adminTheme), '', $adminTheme->version, $adminTheme->path, $adminTheme->source === AdminThemeSource::Local && LocalExtensions::contains($this->paths, $adminTheme->path));
		}

		return $packages;
	}

	/**
	 * Moves a new extension into its folder, at its name.
	 *
	 * @throws InstallException
	 */
	private function place(ExtensionPackage $incoming, string $folder): InstallResult
	{
		$target = LocalExtensions::path($this->paths, $incoming->name);

		if (file_exists($target)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.target_exists', '{folder} already exists, so {label} wasn\'t installed there. Delete or rename that folder first.', folder: $this->paths->relative($target), label: $incoming->label));
		}

		if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775) && ! is_dir(dirname($target))) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.folder_not_made', '{folder} couldn\'t be made.', folder: $this->paths->relative(dirname($target))));
		}

		if (! @rename($incoming->path, $target)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.not_moved', '{label} couldn\'t be moved into {folder}.', label: $incoming->label, folder: $this->paths->relative($folder)));
		}

		return new InstallResult(self::at($incoming, $target));
	}

	/**
	 * Swaps an installed extension's folder for the new one, keeping the
	 * old folder as a backup.
	 *
	 * @throws InstallException
	 */
	private function swap(ExtensionPackage $existing, ExtensionPackage $incoming, string $folder): InstallResult
	{
		if (file_exists("{$existing->path}/.git")) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.git_replace', '{folder} is a git checkout, so git updates it, not an upload.', folder: $this->paths->relative($existing->path)));
		}

		$aside = sprintf('%s/.replaced-%s', $folder, bin2hex(random_bytes(6)));

		if (! @rename($existing->path, $aside)) {
			throw new InstallException((string) $this->messages->t('extension.extension_installer.replace_aside', '{folder} couldn\'t be moved aside to replace it.', folder: $this->paths->relative($existing->path)));
		}

		if (! @rename($incoming->path, $existing->path)) {
			@rename($aside, $existing->path);

			throw new InstallException((string) $this->messages->t('extension.extension_installer.not_replaced', '{folder} couldn\'t be replaced.', folder: $this->paths->relative($existing->path)));
		}

		$backup = $this->backupPath($existing->path);

		$this->remove($backup);

		if (! is_dir(dirname($backup))) {
			@mkdir(dirname($backup), 0775, true);
		}

		if (! @rename($aside, $backup)) {
			$this->remove($aside);
			$backup = null;
		}

		return new InstallResult(self::at($incoming, $existing->path), $existing, $backup === null ? null : $this->paths->relative($backup));
	}

	/**
	 * Removes a folder, if it's there.
	 */
	private function remove(string $folder): void
	{
		try {
			$this->filesystem->removeDirectory($folder);
		} catch (FilesystemException) {
			// Hidden, so discovery skips what's left.
		}
	}

	/**
	 * The package, at its new path.
	 */
	private static function at(ExtensionPackage $package, string $path): ExtensionPackage
	{
		return new ExtensionPackage($package->kind, $package->name, $package->label, $package->namespace, $package->version, $path, true, $package->abandoned, $package->suggest);
	}
}
