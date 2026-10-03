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
 * image, a video, a sound, a document (a PDF, an office file, plain text;
 * D-406), or any other file (a caption track). Each kind has its own
 * metadata fields beside the ones every kind has, is a place field sets
 * attach to (`media:{kind}`, D-341), and has its own upload rules
 * (`MediaUploads`, D-406).
 */
enum MediaKind: string
{
	case Image    = 'image';
	case Video    = 'video';
	case Audio    = 'audio';
	case Document = 'document';
	case File     = 'file';

	/**
	 * The MIME types that are documents.
	 *
	 * @var list<string>
	 */
	public const array DOCUMENT_TYPES = [
		'application/pdf',
		'application/epub+zip',
		'application/rtf',
		'application/msword',
		'application/vnd.ms-excel',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.presentation',
		'text/plain',
		'text/csv',
		'text/markdown'
	];

	/**
	 * Returns the kind's name for people, as the admin offers it.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Image    => 'Images',
			self::Video    => 'Videos',
			self::Audio    => 'Sound',
			self::Document => 'Documents',
			self::File     => 'Other Files'
		};
	}

	/**
	 * Returns the kind's folder name, for an upload path's `{kind}`.
	 */
	public function folder(): string
	{
		return match ($this) {
			self::Image    => 'images',
			self::Video    => 'videos',
			self::Audio    => 'audio',
			self::Document => 'documents',
			self::File     => 'files'
		};
	}

	/**
	 * Returns the kind of a MIME type.
	 */
	public static function fromMime(string $mime): self
	{
		$mime = strtolower($mime);

		if (in_array($mime, self::DOCUMENT_TYPES, true)) {
			return self::Document;
		}

		return match (strstr($mime, '/', true)) {
			'image' => self::Image,
			'video' => self::Video,
			'audio' => self::Audio,
			default => self::File
		};
	}
}
