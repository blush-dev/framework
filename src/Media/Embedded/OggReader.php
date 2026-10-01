<?php

/**
 * Ogg reader.
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
 * Reads Ogg Vorbis and Opus (D-291): the comments in the stream's second
 * packet (title, artist, album, date, genre, track, description,
 * copyright, the encoder, and a picture, noted) and the duration, from
 * the last page's granule position over the stream's sample rate (Opus
 * always counts at 48 kHz, less its pre-skip). A stream that isn't
 * Vorbis or Opus (Theora video) says nothing.
 */
final readonly class OggReader implements EmbeddedReader
{
	/**
	 * How much of the start is read for the headers, and of the end for
	 * the last page.
	 */
	private const int HEAD = 1_048_576;

	private const int TAIL = 65_536;

	/**
	 * Comment names by what they hold.
	 *
	 * @var array<string, string>
	 */
	private const array COMMENTS = [
		'TITLE'       => 'title',
		'ARTIST'      => 'creator',
		'ALBUM'       => 'album',
		'DATE'        => 'created',
		'GENRE'       => 'genre',
		'TRACKNUMBER' => 'track',
		'DESCRIPTION' => 'description',
		'COMMENT'     => 'description',
		'COPYRIGHT'   => 'copyright',
		'ENCODER'     => 'software'
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! in_array($mime, ['audio/ogg', 'video/ogg', 'audio/opus'], true)) {
			return new EmbeddedMetadata();
		}

		$file    = new BinaryFile($path);
		$packets = self::packets($file->read(0, self::HEAD), 2);
		$first   = $packets[0] ?? '';
		$values  = [];

		if (str_starts_with($first, "\x01vorbis")) {
			$rate  = BinaryFile::uint32le($first, 12);
			$skip  = 0;
			$tags  = substr($packets[1] ?? '', 7);
		} elseif (str_starts_with($first, 'OpusHead')) {
			$rate  = 48000;
			$skip  = BinaryFile::uint16le($first, 10);
			$tags  = substr($packets[1] ?? '', 8);
		} else {
			return new EmbeddedMetadata();
		}

		$values = self::comments($tags);
		$serial = BinaryFile::uint32le($file->read(0, 27), 14);
		$last   = self::lastGranule($file->read(max(0, $file->size - self::TAIL), self::TAIL), $serial);

		if ($rate > 0 && $last !== null && $last > $skip) {
			$values['duration'] = ($last - $skip) / $rate;
		}

		return new EmbeddedMetadata($values);
	}

	/**
	 * The first packets of the first stream, put back together from its
	 * pages' segments (a packet ends on a segment shorter than 255).
	 *
	 * @return list<string>
	 */
	private static function packets(string $bytes, int $count): array
	{
		$packets = [];
		$current = '';
		$at      = 0;
		$serial  = null;

		while (count($packets) < $count && $at + 27 <= strlen($bytes) && substr($bytes, $at, 4) === 'OggS') {
			$segments = ord($bytes[$at + 26]);
			$table    = substr($bytes, $at + 27, $segments);
			$data     = $at + 27 + $segments;
			$page     = BinaryFile::uint32le($bytes, $at + 14);

			$serial ??= $page;

			foreach (str_split($table) as $length) {
				$length = ord($length);

				if ($page === $serial) {
					$current .= substr($bytes, $data, $length);

					if ($length < 255) {
						$packets[] = $current;
						$current   = '';
					}
				}

				$data += $length;
			}

			$at = $data;
		}

		return $packets;
	}

	/**
	 * A comment packet's `NAME=value` pairs, after the vendor string.
	 *
	 * @return array<string, mixed>
	 */
	private static function comments(string $packet): array
	{
		$vendor = BinaryFile::uint32le($packet);
		$at     = 4 + $vendor;
		$count  = BinaryFile::uint32le($packet, $at);
		$at    += 4;
		$values = ['software' => EmbeddedMetadata::text(substr($packet, 4, $vendor))];

		for ($i = 0; $i < $count && $at + 4 <= strlen($packet); $i++) {
			$length  = BinaryFile::uint32le($packet, $at);
			$comment = substr($packet, $at + 4, $length);
			$at     += 4 + $length;
			$equals  = strpos($comment, '=');

			if ($equals === false) {
				continue;
			}

			$name  = strtoupper(substr($comment, 0, $equals));
			$value = substr($comment, $equals + 1);

			if ($name === 'METADATA_BLOCK_PICTURE') {
				$values['artwork'] = self::picture($value);
			} elseif (isset(self::COMMENTS[$name]) && ! isset($values[self::COMMENTS[$name]])) {
				$key          = self::COMMENTS[$name];
				$values[$key] = $key === 'created' ? EmbeddedMetadata::date($value) : $value;
			}
		}

		return $values;
	}

	/**
	 * A FLAC picture block, base64 encoded: its MIME type and size.
	 */
	private static function picture(string $encoded): string
	{
		$block = (string) base64_decode($encoded, true);
		$mime  = substr($block, 8, BinaryFile::uint32be($block, 4));

		return sprintf('%s, %s', $mime !== '' ? $mime : 'image', BinaryFile::size(strlen($block)));
	}

	/**
	 * The granule position of the stream's last page in the file's end.
	 */
	private static function lastGranule(string $tail, int $serial): ?int
	{
		$at = strrpos($tail, 'OggS');

		while ($at !== false) {
			if (BinaryFile::uint32le($tail, $at + 14) === $serial && $at + 14 <= strlen($tail)) {
				return BinaryFile::uint64le($tail, $at + 6);
			}

			$at = $at === 0 ? false : strrpos(substr($tail, 0, $at), 'OggS');
		}

		return null;
	}
}
