<?php

/**
 * Uploaded media.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * A file just added to the library (`MediaEditor::upload()`): the file,
 * its reference (the URL it's served at), its key (its path under
 * `user/media`), and its id, `null` when its metadata couldn't be
 * written.
 */
final readonly class UploadedMedia
{
	public function __construct(
		public MediaFile $file,
		public string $reference,
		public string $relative,
		public ?string $id
	) {}
}
