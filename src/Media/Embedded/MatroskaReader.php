<?php

/**
 * Matroska reader.
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
 * Reads WebM and Matroska (D-291) by walking their EBML elements to the
 * first cluster of media: the segment's `Info` (the duration, in its
 * timecode scale, the title, the writing app, and when it was made) and
 * a video track's pixel size. Tags, usually written after the media,
 * aren't read.
 */
final readonly class MatroskaReader implements EmbeddedReader
{
	/**
	 * How much of the start is read; the headers come before the media.
	 */
	private const int LIMIT = 1_048_576;

	private const int SEGMENT = 0x18538067;

	private const int INFO = 0x1549A966;

	private const int TRACKS = 0x1654AE6B;

	private const int TRACK = 0xAE;

	private const int VIDEO = 0xE0;

	private const int CLUSTER = 0x1F43B675;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! in_array($mime, ['video/webm', 'audio/webm', 'video/x-matroska', 'audio/x-matroska'], true)) {
			return new EmbeddedMetadata();
		}

		$bytes = new BinaryFile($path)->read(0, self::LIMIT);

		if (! str_starts_with($bytes, "\x1A\x45\xDF\xA3")) {
			return new EmbeddedMetadata();
		}

		$values = [];

		foreach (self::elements($bytes, 0, strlen($bytes)) as [$id, $start, $end]) {
			if ($id !== self::SEGMENT) {
				continue;
			}

			foreach (self::elements($bytes, $start, $end) as [$child, $from, $to]) {
				if ($child === self::INFO) {
					$values = [...$values, ...self::info($bytes, $from, $to)];
				} elseif ($child === self::TRACKS) {
					$values = [...$values, ...self::tracks($bytes, $from, $to)];
				} elseif ($child === self::CLUSTER) {
					break;
				}
			}
		}

		return new EmbeddedMetadata($values);
	}

	/**
	 * The elements between two offsets: each one's ID and where its data
	 * starts and ends. A size of all ones (unknown, as live streams
	 * write) runs to the end.
	 *
	 * @return iterable<array{int, int, int}>
	 */
	private static function elements(string $bytes, int $from, int $to): iterable
	{
		$at = $from;

		while ($at < $to) {
			$id   = self::vint($bytes, $at, true);
			$size = $id === null ? null : self::vint($bytes, $at + $id[1], false);

			if ($id === null || $size === null) {
				return;
			}

			$start = $at + $id[1] + $size[1];
			$end   = $size[0] === -1 ? $to : min($to, $start + $size[0]);

			yield [$id[0], $start, $end];

			$at = $end;
		}
	}

	/**
	 * A variable-length integer at an offset, and how many bytes it takes:
	 * an ID keeps its length marker, a size doesn't. `-1` is an unknown
	 * size.
	 *
	 * @return ?array{int, int}
	 */
	private static function vint(string $bytes, int $at, bool $id): ?array
	{
		$first = ord($bytes[$at] ?? "\0");

		if ($first === 0) {
			return null;
		}

		$length = 1;

		while ($length <= 8 && ($first & (0x80 >> ($length - 1))) === 0) {
			$length++;
		}

		if ($length > 8 || $at + $length > strlen($bytes)) {
			return null;
		}

		$value = $id ? $first : $first & (0xFF >> $length);
		$ones  = $value === (0xFF >> $length);

		for ($i = 1; $i < $length; $i++) {
			$byte  = ord($bytes[$at + $i]);
			$value = ($value << 8) | $byte;
			$ones  = $ones && $byte === 0xFF;
		}

		return [! $id && $ones ? -1 : $value, $length];
	}

	/**
	 * The segment's `Info`: duration (a float, in timecode scale units,
	 * nanoseconds each by default), title, writing app, and date (in
	 * nanoseconds from 2001).
	 *
	 * @return array<string, mixed>
	 */
	private static function info(string $bytes, int $from, int $to): array
	{
		$scale    = 1_000_000;
		$duration = null;
		$values   = [];

		foreach (self::elements($bytes, $from, $to) as [$id, $start, $end]) {
			$data = substr($bytes, $start, $end - $start);

			match ($id) {
				0x2AD7B1 => $scale = BinaryFile::uintbe($data),
				0x4489   => $duration = self::float($data),
				0x7BA9   => $values['title'] = $data,
				0x5741   => $values['software'] = $data,
				0x4461   => $values['created'] = gmdate('Y-m-d H:i:s', 978_307_200 + intdiv(self::signed($data), 1_000_000_000)) . ' Z',
				default  => null
			};
		}

		if ($duration !== null) {
			$values['duration'] = $duration * $scale / 1_000_000_000;
		}

		return $values;
	}

	/**
	 * A video track's pixel width and height.
	 *
	 * @return array<string, int>
	 */
	private static function tracks(string $bytes, int $from, int $to): array
	{
		foreach (self::elements($bytes, $from, $to) as [$id, $start, $end]) {
			if ($id !== self::TRACK) {
				continue;
			}

			foreach (self::elements($bytes, $start, $end) as [$child, $at, $until]) {
				if ($child !== self::VIDEO) {
					continue;
				}

				$size = [];

				foreach (self::elements($bytes, $at, $until) as [$field, $begin, $finish]) {
					if ($field === 0xB0 || $field === 0xBA) {
						$size[$field === 0xB0 ? 'width' : 'height'] = BinaryFile::uintbe(substr($bytes, $begin, $finish - $begin));
					}
				}

				return $size;
			}
		}

		return [];
	}

	/**
	 * A 4- or 8-byte big-endian float.
	 */
	private static function float(string $data): ?float
	{
		$value = match (strlen($data)) {
			4       => unpack('G', $data),
			8       => unpack('E', $data),
			default => false
		};

		return is_array($value) && is_float($value[1] ?? null) ? $value[1] : null;
	}

	/**
	 * A signed big-endian integer of up to 8 bytes.
	 */
	private static function signed(string $data): int
	{
		$value = BinaryFile::uintbe($data);
		$bits  = strlen($data) * 8;

		return $bits < 64 && $bits > 0 && ($value & (1 << ($bits - 1))) !== 0 ? $value - (1 << $bits) : $value;
	}
}
