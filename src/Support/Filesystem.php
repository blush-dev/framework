<?php

/**
 * Filesystem helpers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

/**
 * Small, safe filesystem operations shared by the framework: atomic writes and
 * path confinement. Stateless, so it can be created wherever it's needed or
 * injected where a test needs to observe it.
 */
final class Filesystem
{
	/**
	 * Writes a file atomically: the contents go to a temp file in the same
	 * directory, which is then renamed over the target, so a reader never
	 * sees a half-written file. Missing directories are created.
	 *
	 * @throws FilesystemException When the file can't be written.
	 */
	public function writeAtomic(string $path, string $contents): void
	{
		$directory = dirname($path);

		if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
			throw new FilesystemException(sprintf('Unable to create directory "%s".', $directory));
		}

		$temp = @tempnam($directory, '.blush-');

		if ($temp === false) {
			throw new FilesystemException(sprintf('Unable to create a temp file in "%s".', $directory));
		}

		if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
			@unlink($temp);
			throw new FilesystemException(sprintf('Unable to write "%s".', $temp));
		}

		@chmod($temp, 0664);

		if (! @rename($temp, $path)) {
			@unlink($temp);
			throw new FilesystemException(sprintf('Unable to move "%s" to "%s".', $temp, $path));
		}
	}

	/**
	 * Joins a relative path onto a root and confirms the result stays inside
	 * the root, rejecting `..` escapes. The path doesn't need to exist.
	 * Returns the normalized absolute path.
	 *
	 * @throws FilesystemException When the path escapes the root.
	 */
	public function confine(string $root, string $relative): string
	{
		$root = $this->normalize($root);
		$path = $this->normalize($root . '/' . $relative);

		if ($path !== $root && ! str_starts_with($path, rtrim($root, '/') . '/')) {
			throw new FilesystemException(sprintf(
				'Path "%s" escapes its root "%s".',
				$relative,
				$root
			));
		}

		return $path;
	}

	/**
	 * Normalizes a path lexically: collapses separators and resolves `.`
	 * and `..` segments without touching the filesystem. Backslashes are
	 * treated as separators.
	 */
	public function normalize(string $path): string
	{
		$path     = str_replace('\\', '/', $path);
		$absolute = str_starts_with($path, '/');
		$parts    = [];

		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}

			if ($segment === '..') {
				if ($parts !== [] && array_last($parts) !== '..') {
					array_pop($parts);
				} elseif (! $absolute) {
					$parts[] = '..';
				}

				continue;
			}

			$parts[] = $segment;
		}

		$normalized = implode('/', $parts);

		return $absolute ? '/' . $normalized : ($normalized === '' ? '.' : $normalized);
	}
}
