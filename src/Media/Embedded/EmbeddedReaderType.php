<?php

/**
 * Embedded reader type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * The built-in embedded metadata readers (D-289, D-291), in the order
 * their values win: XMP (Unicode, and what photo software writes last),
 * then IPTC, then EXIF (the camera's own); then one for each sound and
 * video format the library takes, each reading only its own; then PDF
 * (D-551).
 */
enum EmbeddedReaderType: string
{
	case Xmp      = 'xmp';
	case Iptc     = 'iptc';
	case Exif     = 'exif';
	case Id3      = 'id3';
	case Mp4      = 'mp4';
	case Ogg      = 'ogg';
	case Riff     = 'riff';
	case Matroska = 'matroska';
	case Pdf      = 'pdf';

	/**
	 * @return class-string<EmbeddedReader>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Xmp      => XmpReader::class,
			self::Iptc     => IptcReader::class,
			self::Exif     => ExifReader::class,
			self::Id3      => Id3Reader::class,
			self::Mp4      => Mp4Reader::class,
			self::Ogg      => OggReader::class,
			self::Riff     => RiffReader::class,
			self::Matroska => MatroskaReader::class,
			self::Pdf      => PdfReader::class
		};
	}
}
