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
 * its first video and sound tracks (D-551): a video's pixel size, codec,
 * and frame rate (from its frames' default duration), and a sound's
 * codec, sample rate, and channels. A video's codec is its `format` and
 * its sound's its `audioFormat`; a sound alone's is its `format`. Tags,
 * usually written after the media, aren't read, nor is a bit rate,
 * which Matroska doesn't write.
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

	private const int AUDIO = 0xE1;

	/**
	 * Codecs by codec ID, or the start of one.
	 *
	 * @var array<string, string>
	 */
	private const array CODECS = [
		'V_VP8'             => 'VP8',
		'V_VP9'             => 'VP9',
		'V_AV1'             => 'AV1',
		'V_MPEG4/ISO/AVC'   => 'H.264',
		'V_MPEGH/ISO/HEVC'  => 'HEVC',
		'V_THEORA'          => 'Theora',
		'A_OPUS'            => 'Opus',
		'A_VORBIS'          => 'Vorbis',
		'A_AAC'             => 'AAC',
		'A_FLAC'            => 'FLAC',
		'A_MPEG/L3'         => 'MP3',
		'A_AC3'             => 'AC-3',
		'A_EAC3'            => 'E-AC-3',
		'A_PCM'             => 'PCM'
	];

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
	 * What the first video and sound tracks say.
	 *
	 * @return array<string, mixed>
	 */
	private static function tracks(string $bytes, int $from, int $to): array
	{
		$video = null;
		$sound = null;

		foreach (self::elements($bytes, $from, $to) as [$id, $start, $end]) {
			if ($id !== self::TRACK) {
				continue;
			}

			$track = self::track($bytes, $start, $end);

			// A track that doesn't say its type is a video if it has a size.
			if ($track['type'] === 1 || ($track['type'] === 0 && $track['size'] !== [])) {
				$video ??= $track;
			} elseif ($track['type'] === 2) {
				$sound ??= $track;
			}
		}

		$values = [];

		if ($video !== null) {
			$values = [...$video['size'], 'format' => $video['codec'], 'frameRate' => $video['frame'] > 0 ? 1_000_000_000 / $video['frame'] : null];
		}

		if ($sound !== null) {
			$values[$video === null ? 'format' : 'audioFormat'] = $sound['codec'];
			$values['sampleRate'] = $sound['rate'] > 0 ? (int) round($sound['rate']) : null;
			$values['channels']   = $sound['channels'] > 0 ? $sound['channels'] : null;
		}

		return $values;
	}

	/**
	 * A track entry: its type (1 video, 2 sound), codec, frame duration
	 * in nanoseconds, a video's pixel size, and a sound's sample rate and
	 * channels.
	 *
	 * @return array{type: int, codec: ?string, frame: int, size: array<string, int>, rate: float, channels: int}
	 */
	private static function track(string $bytes, int $from, int $to): array
	{
		$track = ['type' => 0, 'codec' => null, 'frame' => 0, 'size' => [], 'rate' => 0.0, 'channels' => 0];

		foreach (self::elements($bytes, $from, $to) as [$id, $start, $end]) {
			$data = substr($bytes, $start, $end - $start);

			if ($id === 0x83) {
				$track['type'] = BinaryFile::uintbe($data);
			} elseif ($id === 0x86) {
				$track['codec'] = self::codec($data);
			} elseif ($id === 0x23E383) {
				$track['frame'] = BinaryFile::uintbe($data);
			} elseif ($id === self::VIDEO) {
				foreach (self::elements($bytes, $start, $end) as [$field, $begin, $finish]) {
					if ($field === 0xB0 || $field === 0xBA) {
						$track['size'][$field === 0xB0 ? 'width' : 'height'] = BinaryFile::uintbe(substr($bytes, $begin, $finish - $begin));
					}
				}
			} elseif ($id === self::AUDIO) {
				foreach (self::elements($bytes, $start, $end) as [$field, $begin, $finish]) {
					if ($field === 0xB5) {
						$track['rate'] = self::float(substr($bytes, $begin, $finish - $begin)) ?? 0.0;
					} elseif ($field === 0x9F) {
						$track['channels'] = BinaryFile::uintbe(substr($bytes, $begin, $finish - $begin));
					}
				}
			}
		}

		return $track;
	}

	/**
	 * A codec's name from its ID: `A_AAC/MPEG4/LC` is AAC.
	 */
	private static function codec(string $id): ?string
	{
		$id = rtrim($id, "\0");

		foreach (self::CODECS as $prefix => $name) {
			if (str_starts_with($id, $prefix)) {
				return $name;
			}
		}

		return $id === '' ? null : $id;
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
