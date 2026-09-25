<?php

/**
 * Media file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * A resolved media file: where it is, the URL path it's served at, its
 * type and size, and, for raster images, its dimensions.
 */
final readonly class MediaFile
{
	public function __construct(
		public string $path,
		public string $url,
		public string $mime,
		public int $size,
		public ?int $width = null,
		public ?int $height = null
	) {}

	/**
	 * Returns the MIME type's top-level type, such as `image`.
	 */
	public function type(): string
	{
		return strstr($this->mime, '/', true) ?: $this->mime;
	}

	/**
	 * Returns whether the file is an image.
	 */
	public function isImage(): bool
	{
		return $this->type() === 'image';
	}
}
