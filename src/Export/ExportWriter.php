<?php

/**
 * Export writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Writes one export's files into the output folder, and keeps track of
 * them (D-137):
 *
 * - A file is claimed by the first write or copy of it in a run; later
 *   ones are refused, so `public/` files (copied first) win over
 *   rendered URLs, as they do on the live site.
 * - Unchanged files are left alone (rendered ones by content hash,
 *   copied ones by size and modification time), so a deploy tool that
 *   syncs by mtime or checksum only moves what changed.
 * - `prune()` removes the files the previous export wrote that this one
 *   didn't. Files the exporter never wrote (a `.git` folder, a host's
 *   `CNAME`) are never touched.
 */
final class ExportWriter
{
	/**
	 * This run's files: path relative to the output folder => fingerprint.
	 *
	 * @var array<string, string>
	 */
	private array $files = [];

	/**
	 * How many files were written or copied, and how many were current.
	 */
	public private(set) int $written = 0;

	public private(set) int $unchanged = 0;

	/**
	 * @param array<string, string> $previous The previous export's files and fingerprints.
	 */
	public function __construct(
		public readonly string $root,
		private readonly array $previous = [],
		private readonly Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * Returns whether this run already has a file.
	 */
	public function has(string $relative): bool
	{
		return isset($this->files[$relative]);
	}

	/**
	 * Writes a file, unless this run already claimed it. Returns whether
	 * it was claimed.
	 *
	 * @throws ExportException
	 */
	public function write(string $relative, string $contents): bool
	{
		if ($this->has($relative)) {
			return false;
		}

		$fingerprint = 'xxh128:' . hash('xxh128', $contents);
		$path        = $this->path($relative);

		if (($this->previous[$relative] ?? null) === $fingerprint && is_file($path) && filesize($path) === strlen($contents)) {
			$this->unchanged++;
		} else {
			try {
				$this->filesystem->writeAtomic($path, $contents);
			} catch (FilesystemException $error) {
				throw new ExportException($error->getMessage(), 0, $error);
			}

			$this->written++;
		}

		$this->files[$relative] = $fingerprint;

		return true;
	}

	/**
	 * Keeps a file the previous export rendered, as it is, unless this
	 * run already claimed it (`build --incremental`, D-139). Returns
	 * whether it was kept: it must exist, with the previous fingerprint.
	 */
	public function keep(string $relative): bool
	{
		$fingerprint = $this->previous[$relative] ?? null;

		if ($fingerprint === null || $this->has($relative) || ! is_file($this->root . '/' . $relative)) {
			return false;
		}

		$this->files[$relative] = $fingerprint;
		$this->unchanged++;

		return true;
	}

	/**
	 * Returns the previous export's rendered files (not copied ones).
	 *
	 * @return list<string>
	 */
	public function previouslyRendered(): array
	{
		return array_map(strval(...), array_keys(array_filter($this->previous, static fn (string $fingerprint): bool => str_starts_with($fingerprint, 'xxh128:'))));
	}

	/**
	 * Copies a file, unless this run already claimed it. Returns whether
	 * it was claimed.
	 *
	 * @throws ExportException
	 */
	public function copy(string $relative, string $source): bool
	{
		if ($this->has($relative)) {
			return false;
		}

		$path  = $this->path($relative);
		$size  = (int) filesize($source);
		$mtime = (int) filemtime($source);

		if (is_file($path) && ! is_link($path) && filesize($path) === $size && filemtime($path) === $mtime) {
			$this->unchanged++;
		} else {
			if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
				throw new ExportException(sprintf('Unable to create "%s".', dirname($path)));
			}

			if (is_link($path)) {
				unlink($path);
			}

			if (! @copy($source, $path)) {
				throw new ExportException(sprintf('Unable to copy "%s" to "%s".', $source, $path));
			}

			touch($path, $mtime);
			$this->written++;
		}

		$this->files[$relative] = "stat:{$size}:{$mtime}";

		return true;
	}

	/**
	 * Removes the previous export's files that this run didn't write, and
	 * the folders that leaves empty. Returns the removed files.
	 *
	 * @return list<string>
	 */
	public function prune(): array
	{
		$removed = [];

		foreach (array_keys(array_diff_key($this->previous, $this->files)) as $relative) {
			$relative = (string) $relative;

			try {
				$path = $this->path($relative);
			} catch (ExportException) {
				continue;
			}

			if (is_file($path) || is_link($path)) {
				@unlink($path);
				$removed[] = $relative;
			}

			$this->removeEmptyParents(dirname($path));
		}

		return $removed;
	}

	/**
	 * Returns this run's files and fingerprints, for the manifest.
	 *
	 * @return array<string, string>
	 */
	public function files(): array
	{
		return $this->files;
	}

	/**
	 * Returns the absolute path of a file in the output folder.
	 *
	 * @throws ExportException When the path escapes the folder.
	 */
	private function path(string $relative): string
	{
		try {
			return $this->filesystem->confine($this->root, $relative);
		} catch (FilesystemException $error) {
			throw new ExportException($error->getMessage(), 0, $error);
		}
	}

	/**
	 * Removes a folder and its parents, up to the output folder, while
	 * they're empty.
	 */
	private function removeEmptyParents(string $directory): void
	{
		$root = $this->filesystem->normalize($this->root);

		while (str_starts_with($directory, $root . '/') && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
			rmdir($directory);
			$directory = dirname($directory);
		}
	}
}
