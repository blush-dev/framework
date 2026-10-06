<?php

/**
 * Artwork.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * A picture a sound or video file carries (D-551), such as an album's
 * cover: its MIME type and its bytes.
 */
final readonly class Artwork
{
	public function __construct(
		public string $mime,
		public string $bytes
	) {}

	/**
	 * A picture from its bytes, its type found from them, or as given
	 * (`image/jpeg` when neither says); `null` for none.
	 */
	public static function from(string $bytes, string $mime = ''): ?self
	{
		if ($bytes === '') {
			return null;
		}

		$found = match (true) {
			str_starts_with($bytes, "\x89PNG")  => 'image/png',
			str_starts_with($bytes, "\xFF\xD8") => 'image/jpeg',
			str_starts_with($bytes, 'GIF8')     => 'image/gif',
			str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
			default                             => null
		};

		return new self($found ?? (str_starts_with($mime, 'image/') ? $mime : 'image/jpeg'), $bytes);
	}

	/**
	 * What it is, in words: "1400 × 1400 JPEG, 688 KB", without its size
	 * in pixels when that can't be read.
	 */
	public function describe(): string
	{
		$size = @getimagesizefromstring($this->bytes);
		$type = strtoupper(substr($this->mime, (int) strpos($this->mime, '/') + 1));

		return ($size === false || $size[0] === 0 ? '' : "{$size[0]} × {$size[1]} ")
			. ($type === '' ? 'Image' : $type)
			. ', ' . BinaryFile::size(strlen($this->bytes));
	}
}
