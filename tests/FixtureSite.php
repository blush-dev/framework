<?php

/**
 * Fixture site test helper.
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

/**
 * Copies `tests/Fixtures/site` into a scratch directory, so a test can write
 * caches and edit files without touching the fixture.
 */
trait FixtureSite
{
	use TemporaryDirectory;

	/**
	 * Copies the fixture site and returns the copy's root.
	 */
	protected function fixtureSite(): string
	{
		$source = __DIR__ . '/Fixtures/site';
		$target = $this->temporaryDirectory() . '/site';

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		mkdir($target, 0775, true);

		foreach ($files as $file) {
			if (! $file instanceof SplFileInfo) {
				continue;
			}

			$destination = $target . substr($file->getPathname(), strlen($source));

			$file->isDir()
				? mkdir($destination, 0775, true)
				: copy($file->getPathname(), $destination);
		}

		return $target;
	}
}
