<?php

/**
 * Embedded reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * Reads one format of embedded metadata from a media file (D-289). A
 * reader that can't read a file (another format, a missing PHP
 * extension, a damaged file) returns empty metadata; it never throws for
 * what's in the file.
 */
interface EmbeddedReader
{
	/**
	 * Reads the file at `path`, whose MIME type is `mime`.
	 */
	public function read(string $path, string $mime): EmbeddedMetadata;
}
