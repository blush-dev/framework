<?php

/**
 * MP4 reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use Override;

/**
 * Reads MP4 (and M4A and M4V; D-291) by walking its boxes: the duration
 * (`moov/mvhd`), a video track's size (`trak/tkhd`), and the tags an
 * iTunes-style `ilst` holds (title, artist, album, year, genre, track,
 * comment, copyright, encoder, and cover art, noted). The media data
 * (`mdat`) is skipped, never read, wherever it is.
 */
final readonly class Mp4Reader implements EmbeddedReader
{
	/**
	 * The most of `moov` read; it holds indexes, not media, but a long
	 * file's can be large.
	 */
	private const int LIMIT = 16_777_216;

	/**
	 * `ilst` items by what they hold.
	 *
	 * @var array<string, string>
	 */
	private const array ITEMS = [
		"\xA9nam" => 'title',
		"\xA9ART" => 'creator',
		"\xA9alb" => 'album',
		"\xA9day" => 'created',
		"\xA9gen" => 'genre',
		"\xA9cmt" => 'description',
		'desc'    => 'description',
		'cprt'    => 'copyright',
		"\xA9too" => 'software'
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! in_array($mime, ['video/mp4', 'audio/mp4', 'audio/x-m4a', 'video/quicktime'], true)) {
			return new EmbeddedMetadata();
		}

		$file = new BinaryFile($path);

		foreach (self::boxes($file, 0, $file->size) as [$type, $start, $end]) {
			if ($type === 'moov') {
				return new EmbeddedMetadata(self::movie($file->read($start, min($end - $start, self::LIMIT))));
			}
		}

		return new EmbeddedMetadata();
	}

	/**
	 * The boxes between two offsets in a file: each one's type and where
	 * its contents start and end.
	 *
	 * @return iterable<array{string, int, int}>
	 */
	private static function boxes(BinaryFile $file, int $from, int $to): iterable
	{
		$at = $from;

		while ($at + 8 <= $to) {
			$header = $file->read($at, 16);
			$size   = BinaryFile::uint32be($header);
			$type   = substr($header, 4, 4);
			$skip   = 8;

			if ($size === 1) {
				$size = BinaryFile::uint64be($header, 8);
				$skip = 16;
			} elseif ($size === 0) {
				$size = $to - $at;
			}

			if ($size < $skip || strlen($type) !== 4) {
				return;
			}

			yield [$type, $at + $skip, min($at + $size, $to)];

			$at += $size;
		}
	}

	/**
	 * The boxes in a string of box contents.
	 *
	 * @return list<array{string, string}>
	 */
	private static function children(string $bytes): array
	{
		$found = [];
		$at    = 0;

		while ($at + 8 <= strlen($bytes)) {
			$size = BinaryFile::uint32be($bytes, $at);
			$skip = 8;

			if ($size === 1) {
				$size = BinaryFile::uint64be($bytes, $at + 8);
				$skip = 16;
			} elseif ($size === 0) {
				$size = strlen($bytes) - $at;
			}

			if ($size < $skip) {
				break;
			}

			$found[] = [substr($bytes, $at + 4, 4), substr($bytes, $at + $skip, $size - $skip)];
			$at     += $size;
		}

		return $found;
	}

	/**
	 * What `moov` says.
	 *
	 * @return array<string, mixed>
	 */
	private static function movie(string $moov): array
	{
		$values = [];

		foreach (self::children($moov) as [$type, $box]) {
			if ($type === 'mvhd') {
				$long  = ord($box[0] ?? "\0") === 1;
				$scale = BinaryFile::uint32be($box, $long ? 20 : 12);
				$span  = $long ? BinaryFile::uint64be($box, 24) : BinaryFile::uint32be($box, 16);

				$values['duration'] = $scale === 0 ? null : $span / $scale;
			} elseif ($type === 'trak') {
				$values = [...$values, ...self::track($box)];
			} elseif ($type === 'udta') {
				$values = [...self::tags($box), ...$values];
			} elseif ($type === 'meta') {
				$values = [...self::tags($box, true), ...$values];
			}
		}

		return $values;
	}

	/**
	 * A video track's width and height (`tkhd`, 16.16 fixed point).
	 *
	 * @return array<string, int>
	 */
	private static function track(string $trak): array
	{
		foreach (self::children($trak) as [$type, $box]) {
			if ($type === 'tkhd') {
				$long   = ord($box[0] ?? "\0") === 1;
				$offset = $long ? 88 : 76;
				$width  = BinaryFile::uint32be($box, $offset) >> 16;
				$height = BinaryFile::uint32be($box, $offset + 4) >> 16;

				return $width > 0 && $height > 0 ? ['width' => $width, 'height' => $height] : [];
			}
		}

		return [];
	}

	/**
	 * The tags in `udta/meta/ilst` (or `meta/ilst` straight under
	 * `moov`). A `meta` box is a full box, with four bytes of version and
	 * flags before its children, in MP4; QuickTime's has none.
	 *
	 * @return array<string, mixed>
	 */
	private static function tags(string $box, bool $isMeta = false): array
	{
		foreach ($isMeta ? [['meta', $box]] : self::children($box) as [$type, $meta]) {
			if ($type !== 'meta') {
				continue;
			}

			$meta = substr($meta, 4, 4) === 'hdlr' ? $meta : substr($meta, 4);

			foreach (self::children($meta) as [$child, $ilst]) {
				if ($child === 'ilst') {
					return self::items($ilst);
				}
			}
		}

		return [];
	}

	/**
	 * `ilst` items: each a `data` box, with a type and locale, then the
	 * value.
	 *
	 * @return array<string, mixed>
	 */
	private static function items(string $ilst): array
	{
		$values = [];

		foreach (self::children($ilst) as [$type, $item]) {
			$data = null;

			foreach (self::children($item) as [$child, $box]) {
				if ($child === 'data') {
					$data = substr($box, 8);
					break;
				}
			}

			if ($data === null) {
				continue;
			}

			if ($type === 'trkn' && strlen($data) >= 6) {
				$number = BinaryFile::uint16be($data, 2);
				$total  = BinaryFile::uint16be($data, 4);

				$values['track'] = $number === 0 ? null : ($total === 0 ? (string) $number : "{$number}/{$total}");
			} elseif ($type === 'covr') {
				$values['artwork'] = sprintf('%s, %s', str_starts_with($data, "\x89PNG") ? 'image/png' : 'image/jpeg', BinaryFile::size(strlen($data)));
			} elseif (isset(self::ITEMS[$type])) {
				$key          = self::ITEMS[$type];
				$values[$key] = $key === 'created' ? EmbeddedMetadata::date($data) : $data;
			}
		}

		return $values;
	}
}
