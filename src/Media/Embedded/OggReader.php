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
 * copyright, the encoder, and a picture, described, and given by
 * `artwork()`) and the duration, from the last page's granule position
 * over the stream's sample rate (Opus always counts at 48 kHz, less its
 * pre-skip). The first packet gives its codec, channels, and sample rate
 * (an Opus file's is the rate it was made at), and Vorbis its nominal
 * bit rate; otherwise the bit rate is the file's size over its length
 * (D-551). A stream that isn't Vorbis or Opus (Theora video) says
 * nothing.
 */
final readonly class OggReader implements ArtworkReader
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

	private const array MIMES = ['audio/ogg', 'video/ogg', 'audio/opus'];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! in_array($mime, self::MIMES, true)) {
			return new EmbeddedMetadata();
		}

		$file    = new BinaryFile($path);
		$packets = self::packets($file->read(0, self::HEAD), 2);
		$first   = $packets[0] ?? '';
		$values  = [];

		if (str_starts_with($first, "\x01vorbis")) {
			$rate    = BinaryFile::uint32le($first, 12);
			$skip    = 0;
			$tags    = substr($packets[1] ?? '', 7);
			$nominal = BinaryFile::uint32le($first, 20);
			$stream  = ['format' => 'Vorbis', 'channels' => ord($first[11] ?? "\0"), 'sampleRate' => $rate, 'bitrate' => $nominal > 0 && $nominal < 0x80000000 ? $nominal : null];
		} elseif (str_starts_with($first, 'OpusHead')) {
			$rate    = 48000;
			$skip    = BinaryFile::uint16le($first, 10);
			$tags    = substr($packets[1] ?? '', 8);
			$stream  = ['format' => 'Opus', 'channels' => ord($first[9] ?? "\0"), 'sampleRate' => BinaryFile::uint32le($first, 12) ?: 48000];
		} else {
			return new EmbeddedMetadata();
		}

		$values = [...self::comments($tags), ...array_filter($stream, static fn (mixed $value): bool => $value !== null && $value !== 0)];
		$serial = BinaryFile::uint32le($file->read(0, 27), 14);
		$last   = self::lastGranule($file->read(max(0, $file->size - self::TAIL), self::TAIL), $serial);

		if ($rate > 0 && $last !== null && $last > $skip) {
			$values['duration'] = ($last - $skip) / $rate;
			$values['bitrate'] ??= (int) round($file->size * 8 / $values['duration']);
		}

		return new EmbeddedMetadata($values);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function artwork(string $path, string $mime): ?Artwork
	{
		if (! in_array($mime, self::MIMES, true)) {
			return null;
		}

		$packets = self::packets(new BinaryFile($path)->read(0, self::HEAD), 2);
		$first   = $packets[0] ?? '';
		$skip    = match (true) {
			str_starts_with($first, "\x01vorbis") => 7,
			str_starts_with($first, 'OpusHead')   => 8,
			default                               => null
		};

		$block = $skip === null ? null : self::comment(substr($packets[1] ?? '', $skip), 'METADATA_BLOCK_PICTURE');

		return $block === null ? null : self::picture($block);
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
	 * @return list<array{string, string}>
	 */
	private static function pairs(string $packet): array
	{
		$vendor = BinaryFile::uint32le($packet);
		$at     = 4 + $vendor;
		$count  = BinaryFile::uint32le($packet, $at);
		$at    += 4;
		$pairs  = [['ENCODER', substr($packet, 4, $vendor)]];

		for ($i = 0; $i < $count && $at + 4 <= strlen($packet); $i++) {
			$length  = BinaryFile::uint32le($packet, $at);
			$comment = substr($packet, $at + 4, $length);
			$at     += 4 + $length;
			$equals  = strpos($comment, '=');

			if ($equals !== false) {
				$pairs[] = [strtoupper(substr($comment, 0, $equals)), substr($comment, $equals + 1)];
			}
		}

		return $pairs;
	}

	/**
	 * One comment's value, or `null`.
	 */
	private static function comment(string $packet, string $name): ?string
	{
		foreach (self::pairs($packet) as [$key, $value]) {
			if ($key === $name) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * What a comment packet says.
	 *
	 * @return array<string, mixed>
	 */
	private static function comments(string $packet): array
	{
		$values = [];

		foreach (self::pairs($packet) as [$name, $value]) {
			if ($name === 'METADATA_BLOCK_PICTURE') {
				$values['artwork'] = self::picture($value)?->describe();
			} elseif (isset(self::COMMENTS[$name]) && ! isset($values[self::COMMENTS[$name]])) {
				$key          = self::COMMENTS[$name];
				$values[$key] = $key === 'created' ? EmbeddedMetadata::date($value) : EmbeddedMetadata::text($value);
			}
		}

		return $values;
	}

	/**
	 * A FLAC picture block, base64 encoded: its type, MIME type, a
	 * description, its size and colors, then the picture.
	 */
	private static function picture(string $encoded): ?Artwork
	{
		$block = (string) base64_decode($encoded, true);
		$mime  = substr($block, 8, BinaryFile::uint32be($block, 4));
		$at    = 8 + strlen($mime);
		$at   += 4 + BinaryFile::uint32be($block, $at) + 16;

		return Artwork::from(substr($block, $at + 4, BinaryFile::uint32be($block, $at)), $mime);
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
