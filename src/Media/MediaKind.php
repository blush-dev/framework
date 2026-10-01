<?php

/**
 * Media kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * What kind of file a media file is (D-287), from its MIME type: an
 * image, a video, a sound, or any other file (a caption track, a PDF).
 * Each kind has its own metadata fields beside the ones every kind has.
 */
enum MediaKind: string
{
	case Image = 'image';
	case Video = 'video';
	case Audio = 'audio';
	case File  = 'file';

	/**
	 * Returns the kind of a MIME type.
	 */
	public static function fromMime(string $mime): self
	{
		return match (strstr(strtolower($mime), '/', true)) {
			'image' => self::Image,
			'video' => self::Video,
			'audio' => self::Audio,
			default => self::File
		};
	}
}
