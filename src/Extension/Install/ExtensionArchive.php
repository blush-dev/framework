<?php

/**
 * Extension archive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

use ZipArchive;

/**
 * A `.zip` of an extension's folder, read before anything is written
 * (D-392). Every entry is checked first: no absolute paths, no `..`, no
 * symbolic links, and limits on how many files there are and how large
 * they are unpacked. A zip whose files sit in one folder (as GitHub's
 * release zips do, `acme-hello-1a2b3c/…`) is unpacked from inside it.
 * Entries are written one by one, never with `extractTo()`, so nothing
 * lands outside the folder it's unpacked into.
 */
final class ExtensionArchive
{
	/**
	 * The most files an archive may hold.
	 */
	public const int MAX_FILES = 5000;

	/**
	 * The most an archive's files may come to, unpacked, in bytes.
	 */
	public const int MAX_UNPACKED = 100 * 1024 * 1024;

	/**
	 * Entries left out: macOS's resource forks and folder settings.
	 */
	private const string SKIPPED = '#(^|/)(__MACOSX|\.DS_Store)(/|$)#';

	/**
	 * The files to unpack, by their path in the archive, with their paths
	 * relative to the extension's folder.
	 *
	 * @var array<int, string>
	 */
	private array $entries = [];

	private function __construct(
		private readonly ZipArchive $zip,
		private readonly string $name
	) {}

	/**
	 * Opens and checks an archive.
	 *
	 * @param  string $file The uploaded file.
	 * @param  string $name Its name, for messages.
	 * @throws InstallException When it isn't a zip, or isn't safe to unpack.
	 */
	public static function open(string $file, string $name): self
	{
		if (! class_exists(ZipArchive::class)) {
			throw new InstallException('This server\'s PHP can\'t read .zip files (it has no zip extension), so nothing can be installed here.');
		}

		$zip = new ZipArchive();

		if ($zip->open($file, ZipArchive::RDONLY) !== true) {
			throw new InstallException(sprintf('%s isn\'t a .zip file Blush can read.', $name));
		}

		$archive = new self($zip, $name);
		$archive->check();

		return $archive;
	}

	/**
	 * Whether the extension's folder has a file, by its path in the
	 * folder.
	 */
	public function has(string $path): bool
	{
		return in_array($path, $this->entries, true);
	}

	/**
	 * Unpacks the extension's files into a folder, which must exist.
	 *
	 * @throws InstallException When a file can't be written.
	 */
	public function extractTo(string $folder): void
	{
		foreach ($this->entries as $index => $path) {
			$target = "{$folder}/{$path}";
			$parent = dirname($target);

			if (! is_dir($parent) && ! @mkdir($parent, 0775, true) && ! is_dir($parent)) {
				throw new InstallException(sprintf('%s couldn\'t be unpacked: a folder couldn\'t be made.', $this->name));
			}

			$stream = $this->zip->getStream($this->zip->getNameIndex($index) ?: '');
			$out    = @fopen($target, 'xb');

			if ($stream === false || $out === false) {
				throw new InstallException(sprintf('%s couldn\'t be unpacked: %s couldn\'t be written.', $this->name, $path));
			}

			stream_copy_to_stream($stream, $out);
			fclose($stream);
			fclose($out);
		}
	}

	public function close(): void
	{
		$this->zip->close();
	}

	/**
	 * Checks every entry, and works out the folder the files are in.
	 *
	 * @throws InstallException
	 */
	private function check(): void
	{
		if ($this->zip->numFiles > self::MAX_FILES) {
			throw new InstallException(sprintf('%s holds more than %d files.', $this->name, self::MAX_FILES));
		}

		$files = [];
		$total = 0;

		for ($index = 0; $index < $this->zip->numFiles; $index++) {
			$stat = $this->zip->statIndex($index);

			if ($stat === false) {
				throw new InstallException(sprintf('%s is damaged.', $this->name));
			}

			$path = $stat['name'];

			if (preg_match(self::SKIPPED, $path) === 1) {
				continue;
			}

			if (! self::safe($path)) {
				throw new InstallException(sprintf('%s has a file that would land outside its folder (%s), so it wasn\'t unpacked.', $this->name, $path));
			}

			$system     = 0;
			$attributes = 0;

			$this->zip->getExternalAttributesIndex($index, $system, $attributes);

			if ($system === ZipArchive::OPSYS_UNIX && (((is_int($attributes) ? $attributes : 0) >> 16) & 0o170000) === 0o120000) {
				throw new InstallException(sprintf('%s has a symbolic link (%s), so it wasn\'t unpacked.', $this->name, $path));
			}

			$total += $stat['size'];

			if ($total > self::MAX_UNPACKED) {
				throw new InstallException(sprintf('%s comes to more than %d MB unpacked.', $this->name, self::MAX_UNPACKED / 1024 / 1024));
			}

			if (! str_ends_with($path, '/')) {
				$files[$index] = $path;
			}
		}

		$this->entries = self::unwrap($files);
	}

	/**
	 * The files relative to the extension's folder: the archive's root,
	 * or the one folder everything is in.
	 *
	 * @param  array<int, string> $files
	 * @return array<int, string>
	 */
	private static function unwrap(array $files): array
	{
		$tops = array_unique(array_map(static fn (string $path): string => explode('/', $path, 2)[0], $files));

		if (count($tops) !== 1 || array_any($files, static fn (string $path): bool => ! str_contains($path, '/'))) {
			return $files;
		}

		$prefix = $tops[array_key_first($tops)] . '/';

		return array_map(static fn (string $path): string => substr($path, strlen($prefix)), $files);
	}

	/**
	 * Whether a path stays inside the folder it's unpacked into.
	 */
	private static function safe(string $path): bool
	{
		if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
			return false;
		}

		return ! in_array('..', explode('/', $path), true);
	}
}
