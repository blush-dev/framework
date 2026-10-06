<?php

/**
 * Artwork reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * An embedded reader that can also give the picture a file carries
 * (D-551), such as a song's cover art, which its metadata only
 * describes (`artwork`). Like reading, it never throws for what's in the
 * file: a file without one, or one it can't read, gives `null`.
 */
interface ArtworkReader extends EmbeddedReader
{
	/**
	 * The picture in the file at `path`, whose MIME type is `mime`.
	 */
	public function artwork(string $path, string $mime): ?Artwork;
}
