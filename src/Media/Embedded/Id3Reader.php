<?php

/**
 * ID3 reader.
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
 * Reads MP3s (D-291): ID3v2 tags (2.2, 2.3, and 2.4, in any of their text
 * encodings), then ID3v1 for what those don't have, and from the first
 * frame, its MPEG version, sample rate, and channels (D-551), and the
 * duration and bit rate: from its Xing or VBRI header (variable bit rate,
 * the bit rate an average) or else its own bit rate and the size of the
 * audio (constant). Embedded artwork (`APIC`) is described, and given by
 * `artwork()`.
 */
final readonly class Id3Reader implements ArtworkReader
{
	/**
	 * ID3v2 frames by what they hold, 2.3/2.4 names then 2.2's.
	 *
	 * @var array<string, list<string>>
	 */
	private const array FRAMES = [
		'title'     => ['TIT2', 'TT2'],
		'creator'   => ['TPE1', 'TP1'],
		'album'     => ['TALB', 'TAL'],
		'track'     => ['TRCK', 'TRK'],
		'genre'     => ['TCON', 'TCO'],
		'created'   => ['TDRC', 'TYER', 'TDRL', 'TYE'],
		'copyright' => ['TCOP', 'TCR'],
		'software'  => ['TSSE', 'TENC', 'TSS', 'TEN']
	];

	/**
	 * Bit rates (kbit/s) of Layer III by bit rate index: MPEG-1, then
	 * MPEG-2 and 2.5.
	 *
	 * @var array<int, list<int>>
	 */
	private const array BITRATES = [
		1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
		2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160]
	];

	/**
	 * Sample rates by MPEG version bits and index.
	 *
	 * @var array<int, list<int>>
	 */
	private const array RATES = [
		3 => [44100, 48000, 32000],
		2 => [22050, 24000, 16000],
		0 => [11025, 12000, 8000]
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if ($mime !== 'audio/mpeg') {
			return new EmbeddedMetadata();
		}

		$file   = new BinaryFile($path);
		$values = [];
		$start  = 0;
		$header = $file->read(0, 10);

		if (str_starts_with($header, 'ID3') && strlen($header) === 10) {
			$size   = self::syncsafe(substr($header, 6, 4));
			$start  = 10 + $size + ((ord($header[5]) & 0x10) !== 0 ? 10 : 0);
			$values = self::v2(ord($header[3]), self::frames(ord($header[3]), ord($header[5]), $file->read(10, min($size, 4_194_304))));
		}

		$tail = $file->read($file->size - 128, 128);
		$end  = $file->size;

		if (str_starts_with($tail, 'TAG') && strlen($tail) === 128) {
			$values = [...self::v1($tail), ...$values];
			$end   -= 128;
		}

		return new EmbeddedMetadata([...$values, ...self::stream($file, $start, $end)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function artwork(string $path, string $mime): ?Artwork
	{
		if ($mime !== 'audio/mpeg') {
			return null;
		}

		$file   = new BinaryFile($path);
		$header = $file->read(0, 10);

		if (! str_starts_with($header, 'ID3') || strlen($header) !== 10) {
			return null;
		}

		$frames = self::frames(ord($header[3]), ord($header[5]), $file->read(10, min(self::syncsafe(substr($header, 6, 4)), 4_194_304)));
		$frame  = $frames['APIC'] ?? $frames['PIC'] ?? null;

		return $frame === null ? null : self::picture($frame, ord($header[3]) === 2);
	}

	/**
	 * A 28-bit number written 7 bits a byte.
	 */
	private static function syncsafe(string $bytes): int
	{
		$value = 0;

		foreach (str_split(str_pad($bytes, 4, "\0")) as $byte) {
			$value = ($value << 7) | (ord($byte) & 0x7F);
		}

		return $value;
	}

	/**
	 * An ID3v2 tag's frames, by ID, the first of each.
	 *
	 * @return array<string, string>
	 */
	private static function frames(int $version, int $flags, string $tag): array
	{
		// A whole tag written unsynchronized (2.2 and 2.3): FF 00 is FF.
		if (($flags & 0x80) !== 0 && $version < 4) {
			$tag = str_replace("\xFF\x00", "\xFF", $tag);
		}

		$at = 0;

		// An extended header is skipped: 2.3 counts its size without
		// itself, 2.4 with, as a syncsafe number.
		if (($flags & 0x40) !== 0 && $version >= 3) {
			$at = $version === 3 ? 4 + BinaryFile::uint32be($tag) : self::syncsafe(substr($tag, 0, 4));
		}

		$short  = $version === 2;
		$frames = [];

		while ($at + ($short ? 6 : 10) <= strlen($tag)) {
			$id = substr($tag, $at, $short ? 3 : 4);

			if (preg_match('/^[A-Z0-9]{3,4}$/', $id) !== 1) {
				break;
			}

			$size = match (true) {
				$short         => BinaryFile::uintbe(substr($tag, $at + 3, 3)),
				$version === 4 => self::syncsafe(substr($tag, $at + 4, 4)),
				default        => BinaryFile::uint32be($tag, $at + 4)
			};

			$data = substr($tag, $at + ($short ? 6 : 10), $size);
			$at  += ($short ? 6 : 10) + $size;

			$frames[$id] ??= $data;
		}

		return $frames;
	}

	/**
	 * What an ID3v2 tag's frames say.
	 *
	 * @param  array<string, string> $frames
	 * @return array<string, mixed>
	 */
	private static function v2(int $version, array $frames): array
	{
		$short  = $version === 2;
		$values = [];

		foreach (self::FRAMES as $key => $ids) {
			foreach ($ids as $id) {
				$text = self::text($frames[$id] ?? '');

				if ($text !== '') {
					$values[$key] = $key === 'created' ? EmbeddedMetadata::date($text) : $text;
					break;
				}
			}
		}

		if (isset($values['genre'])) {
			// "(17)Rock" and "(17)" name a genre by its ID3v1 number.
			$values['genre'] = trim((string) preg_replace('/^\(\d+\)/', '', $values['genre'])) ?: $values['genre'];
		}

		$comment = $frames['COMM'] ?? $frames['COM'] ?? null;

		if ($comment !== null && strlen($comment) > 4) {
			// Encoding, language, a description, then the comment.
			$parts = self::strings(ord($comment[0]), substr($comment, 4));

			$values['description'] = $parts[1] ?? '';
		}

		$picture = $frames['APIC'] ?? $frames['PIC'] ?? null;
		$artwork = $picture === null ? null : self::picture($picture, $short);

		if ($artwork !== null) {
			$values['artwork'] = $artwork->describe();
		}

		return $values;
	}

	/**
	 * A picture frame's picture: its encoding, its MIME type (2.2: a
	 * three-letter format), its picture type, a description in that
	 * encoding, then the picture.
	 */
	private static function picture(string $frame, bool $short): ?Artwork
	{
		if (strlen($frame) < 5) {
			return null;
		}

		$encoding = ord($frame[0]);

		if ($short) {
			$mime = 'image/' . strtolower(str_replace('JPG', 'jpeg', substr($frame, 1, 3)));
			$at   = 5;
		} else {
			$end  = strpos($frame, "\0", 1);

			if ($end === false) {
				return null;
			}

			$mime = substr($frame, 1, $end - 1);
			$at   = $end + 2;
		}

		// The description ends with a null, two in UTF-16, on a character.
		if ($encoding === 1 || $encoding === 2) {
			while ($at + 1 < strlen($frame) && substr($frame, $at, 2) !== "\0\0") {
				$at += 2;
			}

			$at += 2;
		} else {
			$end = strpos($frame, "\0", $at);
			$at  = $end === false ? strlen($frame) : $end + 1;
		}

		return Artwork::from((string) substr($frame, $at), $mime);
	}

	/**
	 * A text frame's text: its encoding byte, then text; a list (2.4
	 * separates values with a null) joins with commas.
	 */
	private static function text(string $frame): string
	{
		if ($frame === '') {
			return '';
		}

		return implode(', ', array_filter(self::strings(ord($frame[0]), substr($frame, 1)), static fn (string $part): bool => $part !== ''));
	}

	/**
	 * Null-separated strings in an ID3 encoding: 0 Latin-1, 1 UTF-16 with
	 * a byte order mark, 2 UTF-16BE, 3 UTF-8.
	 *
	 * @return list<string>
	 */
	private static function strings(int $encoding, string $bytes): array
	{
		if ($encoding === 1 || $encoding === 2) {
			$parts = [];

			foreach (preg_split('/(?:\x00\x00)(?=(?:..)*$)/s', $bytes) ?: [] as $part) {
				$big     = $encoding === 2 || str_starts_with($part, "\xFE\xFF");
				$part    = preg_replace('/^(\xFF\xFE|\xFE\xFF)/', '', $part) ?? '';
				$parts[] = EmbeddedMetadata::text((string) mb_convert_encoding($part, 'UTF-8', $big ? 'UTF-16BE' : 'UTF-16LE'));
			}

			return $parts;
		}

		return array_map(static fn (string $part): string => EmbeddedMetadata::text($encoding === 0 ? mb_convert_encoding($part, 'UTF-8', 'ISO-8859-1') : $part), explode("\0", $bytes));
	}

	/**
	 * An ID3v1 tag's title, artist, album, year, and comment.
	 *
	 * @return array<string, string>
	 */
	private static function v1(string $tag): array
	{
		$field = static fn (int $at, int $length): string => EmbeddedMetadata::text(rtrim(substr($tag, $at, $length), "\0 "));

		return array_filter([
			'title'       => $field(3, 30),
			'creator'     => $field(33, 30),
			'album'       => $field(63, 30),
			'created'     => EmbeddedMetadata::date($field(93, 4)),
			'description' => $field(97, 28)
		], static fn (string $value): bool => $value !== '');
	}

	/**
	 * What the first Layer III frame after the tag says (D-551): its
	 * `format`, `sampleRate`, `channels`, `duration` in seconds, and
	 * `bitrate`; none, without one.
	 *
	 * @return array<string, mixed>
	 */
	private static function stream(BinaryFile $file, int $start, int $end): array
	{
		$bytes = $file->read($start, 65_536);

		for ($at = 0; $at + 4 < strlen($bytes); $at++) {
			if (ord($bytes[$at]) !== 0xFF || (ord($bytes[$at + 1]) & 0xE0) !== 0xE0) {
				continue;
			}

			$b1      = ord($bytes[$at + 1]);
			$b2      = ord($bytes[$at + 2]);
			$b3      = ord($bytes[$at + 3]);
			$version = ($b1 >> 3) & 0x3;
			$layer   = ($b1 >> 1) & 0x3;
			$index   = $b2 >> 4;
			$rate    = self::RATES[$version][($b2 >> 2) & 0x3] ?? null;

			// Layer III only, and a real bit rate and sample rate.
			if ($layer !== 1 || $version === 1 || $index === 0 || $index === 15 || $rate === null) {
				continue;
			}

			$mpeg1   = $version === 3;
			$mono    = ($b3 >> 6) === 3;
			$samples = $mpeg1 ? 1152 : 576;
			$side    = $mpeg1 ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
			$xing    = substr($bytes, $at + 4 + $side, 16);
			$vbri    = substr($bytes, $at + 36, 18);
			$audio   = $end - $start - $at;
			$found   = [
				'format'     => sprintf('MPEG-%s Layer III', $mpeg1 ? '1' : ($version === 2 ? '2' : '2.5')),
				'sampleRate' => $rate,
				'channels'   => $mono ? 1 : 2
			];

			$frames = match (true) {
				(str_starts_with($xing, 'Xing') || str_starts_with($xing, 'Info')) && (BinaryFile::uint32be($xing, 4) & 0x1) !== 0 => BinaryFile::uint32be($xing, 8),
				str_starts_with($vbri, 'VBRI') => BinaryFile::uint32be($vbri, 14),
				default                        => null
			};

			if ($frames !== null) {
				// A variable bit rate: the frames counted, and the average.
				$duration = $frames * $samples / $rate;

				return [...$found, 'duration' => $duration, 'bitrate' => $duration > 0 ? (int) round($audio * 8 / $duration) : null];
			}

			// Constant bit rate: the audio's size over its rate.
			$bitrate = self::BITRATES[$mpeg1 ? 1 : 2][$index] * 1000;

			return [...$found, 'duration' => $audio * 8 / $bitrate, 'bitrate' => $bitrate];
		}

		return [];
	}
}
