<?php

/**
 * Temporary directory test helper.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use PHPUnit\Framework\Attributes\After;

/**
 * Gives a test case a scratch directory that is created on first use and
 * removed after each test.
 */
trait TemporaryDirectory
{
	private ?string $temporaryDirectory = null;

	/**
	 * Returns the scratch directory, creating it on first use.
	 */
	protected function temporaryDirectory(): string
	{
		if ($this->temporaryDirectory === null) {
			$this->temporaryDirectory = sys_get_temp_dir() . '/blush-tests-' . bin2hex(random_bytes(8));
			mkdir($this->temporaryDirectory, 0775, true);
		}

		return $this->temporaryDirectory;
	}

	/**
	 * Writes a file inside the scratch directory, creating directories as
	 * needed, and returns its path.
	 */
	protected function writeTemporaryFile(string $relative, string $contents): string
	{
		$path = $this->temporaryDirectory() . '/' . $relative;

		if (! is_dir(dirname($path))) {
			mkdir(dirname($path), 0775, true);
		}

		file_put_contents($path, $contents);

		return $path;
	}

	#[After]
	protected function removeTemporaryDirectory(): void
	{
		if ($this->temporaryDirectory === null || ! is_dir($this->temporaryDirectory)) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->temporaryDirectory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $file) {
			if ($file instanceof SplFileInfo) {
				$file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
			}
		}

		rmdir($this->temporaryDirectory);
		$this->temporaryDirectory = null;
	}
}
