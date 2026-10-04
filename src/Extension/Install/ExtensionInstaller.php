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
use Blush\Core\Paths;
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionNamespace;
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
 * - it holds another kind of extension, or none;
 * - an extension of another kind has its name;
 * - its manifest doesn't pass, or its namespace is reserved or another
 *   installed extension's;
 * - Composer installed one with its name (Composer updates it);
 * - its `composer.json` requires packages besides PHP, its extensions, and
 *   Blush, since a folder extension has no `vendor/` of its own;
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

	/**
	 * Packages a folder extension may require: they're already there.
	 */
	private const string PLATFORM = '#^(php|php-64bit|ext-.+|lib-.+|composer/installers|blush-dev/framework)$#';

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * Why nothing of a kind can be installed here, or `null`.
	 */
	public function problem(ExtensionKind $kind): ?string
	{
		if (! class_exists(ZipArchive::class)) {
			return 'This server\'s PHP can\'t read .zip files (it has no zip extension).';
		}

		$folder   = $this->paths->extensions;
		$writable = is_dir($folder) ? is_writable($folder) : is_writable(dirname($folder));

		return $writable ? null : sprintf('The web server can\'t write to %s.', $this->paths->relative($folder));
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
			throw new InstallException($problem);
		}

		$folder = $this->paths->extensions;

		if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
			throw new InstallException(sprintf('%s couldn\'t be made.', $this->paths->relative($folder)));
		}

		$archive = ExtensionArchive::open($file, $name);
		$staging = sprintf('%s/.install-%s', $folder, bin2hex(random_bytes(6)));

		try {
			self::expect($archive, $kind, $name);

			if (! @mkdir($staging, 0775)) {
				throw new InstallException(sprintf('%s couldn\'t be unpacked into %s.', $name, $this->paths->relative($folder)));
			}

			$archive->extractTo($staging);

			$incoming = $this->read($kind, $staging, $name);
			$existing = $this->check($incoming, $name);

			if ($existing !== null && ! $replace) {
				throw new InstallClash($existing, $incoming);
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
			throw new InstallException(sprintf('No %s named %s is installed in %s.', $kind->label(), $name, $this->paths->relative($this->paths->extensions)));
		}

		$backup = $this->backupPath($existing->path);
		$older  = $this->backupOf($kind, $existing->path, $name);

		if ($older === null) {
			throw new InstallException(sprintf('There\'s no earlier version of %s to roll back to.', $existing->label));
		}

		if (file_exists("{$existing->path}/.git")) {
			throw new InstallException(sprintf('%s is a git checkout, so git changes its version, not a rollback.', $this->paths->relative($existing->path)));
		}

		$this->check($older, $existing->label);

		$aside = sprintf('%s/.rollback-%s', $this->paths->extensions, bin2hex(random_bytes(6)));

		if (! @rename($existing->path, $aside)) {
			throw new InstallException(sprintf('%s couldn\'t be moved aside to roll it back.', $this->paths->relative($existing->path)));
		}

		if (! @rename($backup, $existing->path)) {
			@rename($aside, $existing->path);

			throw new InstallException(sprintf('%s couldn\'t be rolled back.', $existing->label));
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
			throw new InstallException(sprintf('The backup of %s couldn\'t be removed.', $this->paths->relative($path)), previous: $error);
		}

		// Its vendor's folder goes once it's empty.
		if (is_dir(dirname($backup)) && (scandir(dirname($backup)) ?: []) === ['.', '..']) {
			@rmdir(dirname($backup));
		}
	}

	/**
	 * Checks that the archive holds the kind of extension expected.
	 *
	 * @throws InstallException
	 */
	private static function expect(ExtensionArchive $archive, ExtensionKind $kind, string $name): void
	{
		$holds = static fn (ExtensionKind $kind): bool => array_any(
			ManifestFile::FORMATS,
			static fn (string $format): bool => $archive->has("{$kind->manifest()}.{$format}")
		);

		if ($holds($kind)) {
			return;
		}

		$other = array_find(ExtensionKind::cases(), $holds);

		if ($other !== null) {
			throw new InstallException(sprintf('%s is %s, not %s.', $name, self::a($other), self::a($kind)), $other);
		}

		throw new InstallException(sprintf('%s has no %s.json in it, so it isn\'t %s.', $name, $kind->manifest(), self::a($kind)));
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
				ExtensionKind::Plugin   => $this->plugin($folder),
				ExtensionKind::Theme    => $this->theme($folder),
				ExtensionKind::IconPack => $this->pack($folder)
			};
		} catch (ExtensionException | ThemeException $error) {
			$reason = str_replace($folder, $kind->manifest(), $error->getMessage());

			throw new InstallException(sprintf('%s can\'t be installed: %s', $name, $reason), previous: $error);
		}
	}

	/**
	 * @throws ExtensionException
	 */
	private function plugin(string $folder): ExtensionPackage
	{
		$plugin = LocalPluginFinder::manifest(ManifestFile::find($folder, ExtensionKind::Plugin)[0] ?? '');

		return new ExtensionPackage(ExtensionKind::Plugin, $plugin->name, $plugin->label, $plugin->namespace, $plugin->version, $folder, true);
	}

	/**
	 * @throws ThemeException
	 */
	private function theme(string $folder): ExtensionPackage
	{
		$theme = ThemeManifest::fromArray($folder, ComposerJson::fill(ThemeDiscovery::read($folder) ?? [], $folder));

		if ($theme->name === Themes::DEFAULT) {
			throw new ThemeException(sprintf('"%s" is the framework default theme\'s name.', Themes::DEFAULT));
		}

		return new ExtensionPackage(ExtensionKind::Theme, $theme->name, $theme->label, $theme->namespace, $theme->version, $folder, true);
	}

	/**
	 * @throws ExtensionException
	 */
	private function pack(string $folder): ExtensionPackage
	{
		$pack = IconPack::fromArray($folder, ComposerJson::fill(ManifestFile::read(ManifestFile::find($folder, ExtensionKind::IconPack)[0] ?? ''), $folder));

		return new ExtensionPackage(ExtensionKind::IconPack, $pack->name, $pack->label, $pack->namespace, $pack->version, $folder, true);
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
			throw new InstallException(sprintf('%s can\'t be installed: its namespace, "%s", is reserved.', $name, $incoming->namespace));
		}

		$existing = null;

		foreach ($this->installed() as $package) {
			if ($package->name === $incoming->name && $package->kind !== $incoming->kind) {
				throw new InstallException(sprintf('%s can\'t be installed: %s is installed as %s.', $name, $package->name, self::a($package->kind)));
			}

			if ($package->name === $incoming->name) {
				if (! $package->local) {
					throw new InstallException(sprintf('%s is installed with Composer, so Composer updates it.', $package->name));
				}

				$existing = $package;
			} elseif ($package->namespace === $incoming->namespace) {
				throw new InstallException(sprintf('%s can\'t be installed: its namespace, "%s", is the %s %s\'s.', $name, $incoming->namespace, $package->kind->label(), $package->name));
			}
		}

		$require  = ComposerJson::read($incoming->path)['require'] ?? [];
		$packages = array_values(array_filter(
			array_map(strval(...), array_keys(is_array($require) ? $require : [])),
			static fn (string $package): bool => preg_match(self::PLATFORM, $package) !== 1
		));

		if ($packages !== []) {
			throw new InstallException(sprintf('%s needs Composer packages (%s), so it has to be installed with Composer.', $name, implode(', ', $packages)));
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
				throw new InstallException(sprintf(
					'%s can\'t be installed: %s has a PHP error on line %d (%s).',
					$name,
					substr($file->getPathname(), strlen($folder) + 1),
					$error->getLine(),
					$error->getMessage()
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
			$plugins = PluginDiscovery::forPaths($this->paths)->discover()->manifests;
		} catch (ExtensionException $error) {
			throw new InstallException($error->getMessage(), previous: $error);
		}

		$packages = [];

		foreach ($plugins as $plugin) {
			$packages[] = new ExtensionPackage(ExtensionKind::Plugin, $plugin->name, $plugin->label, $plugin->namespace, $plugin->version, $plugin->path, $plugin->source === PluginSource::Local && LocalExtensions::contains($this->paths, $plugin->path));
		}

		foreach (new ThemeDiscovery($this->paths)->discover()->all() as $theme) {
			$packages[] = new ExtensionPackage(ExtensionKind::Theme, $theme->name, $theme->label, $theme->namespace, $theme->version, $theme->path, $theme->source === ThemeSource::Local && LocalExtensions::contains($this->paths, $theme->path));
		}

		foreach (new IconPackDiscovery($this->paths)->discover()->all() as $pack) {
			$packages[] = new ExtensionPackage(ExtensionKind::IconPack, $pack->name, $pack->label, $pack->namespace, $pack->version, $pack->path, $pack->source === IconPackSource::Local && LocalExtensions::contains($this->paths, $pack->path));
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
			throw new InstallException(sprintf('%s already exists, so %s wasn\'t installed there. Delete or rename that folder first.', $this->paths->relative($target), $incoming->label));
		}

		if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775) && ! is_dir(dirname($target))) {
			throw new InstallException(sprintf('%s couldn\'t be made.', $this->paths->relative(dirname($target))));
		}

		if (! @rename($incoming->path, $target)) {
			throw new InstallException(sprintf('%s couldn\'t be moved into %s.', $incoming->label, $this->paths->relative($folder)));
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
			throw new InstallException(sprintf('%s is a git checkout, so git updates it, not an upload.', $this->paths->relative($existing->path)));
		}

		$aside = sprintf('%s/.replaced-%s', $folder, bin2hex(random_bytes(6)));

		if (! @rename($existing->path, $aside)) {
			throw new InstallException(sprintf('%s couldn\'t be moved aside to replace it.', $this->paths->relative($existing->path)));
		}

		if (! @rename($incoming->path, $existing->path)) {
			@rename($aside, $existing->path);

			throw new InstallException(sprintf('%s couldn\'t be replaced.', $this->paths->relative($existing->path)));
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
		return new ExtensionPackage($package->kind, $package->name, $package->label, $package->namespace, $package->version, $path, true);
	}

	/**
	 * "a plugin", "a theme", "an icon pack".
	 */
	private static function a(ExtensionKind $kind): string
	{
		return ($kind === ExtensionKind::IconPack ? 'an ' : 'a ') . $kind->label();
	}
}
