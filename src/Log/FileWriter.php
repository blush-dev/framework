<?php

/**
 * File log writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Override;

/**
 * Appends log lines to a file, creating its directory on first write. Each
 * line is written with an exclusive lock so concurrent requests don't
 * interleave.
 */
final readonly class FileWriter implements LogWriter
{
	public function __construct(private string $path)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(Level $level, string $line): void
	{
		$directory = dirname($this->path);

		if (! is_dir($directory)) {
			@mkdir($directory, 0775, true);
		}

		// A logger must never take the request down with it, so a failed
		// write is dropped silently.
		@file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}
