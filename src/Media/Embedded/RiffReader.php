<?php

/**
 * RIFF reader.
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
 * Reads WAV (D-291): the duration, from the `data` chunk's size over the
 * `fmt ` chunk's byte rate, and the tags in a `LIST` chunk of type
 * `INFO` (title, artist, album, date, genre, track, comment, copyright,
 * and software). The `fmt ` chunk also gives its encoding, channels,
 * sample rate, and bit rate (D-551).
 */
final readonly class RiffReader implements EmbeddedReader
{
	/**
	 * `INFO` chunks by what they hold.
	 *
	 * @var array<string, string>
	 */
	private const array INFO = [
		'INAM' => 'title',
		'IART' => 'creator',
		'IPRD' => 'album',
		'ICRD' => 'created',
		'IGNR' => 'genre',
		'ITRK' => 'track',
		'ICMT' => 'description',
		'ICOP' => 'copyright',
		'ISFT' => 'software'
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! in_array($mime, ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'], true)) {
			return new EmbeddedMetadata();
		}

		$file = new BinaryFile($path);
		$head = $file->read(0, 12);

		if (substr($head, 0, 4) !== 'RIFF' || substr($head, 8, 4) !== 'WAVE') {
			return new EmbeddedMetadata();
		}

		$values = [];
		$rate   = 0;
		$at     = 12;

		while ($at + 8 <= $file->size) {
			$header = $file->read($at, 8);
			$id     = substr($header, 0, 4);
			$size   = BinaryFile::uint32le($header, 4);

			if ($id === 'fmt ') {
				$format = $file->read($at + 8, 16);
				$rate   = BinaryFile::uint32le($format, 8);
				$values = [...$values, ...self::format($format)];
			} elseif ($id === 'data' && $rate > 0) {
				$values['duration'] = $size / $rate;
			} elseif ($id === 'LIST' && $size <= 1_048_576 && substr($list = $file->read($at + 8, $size), 0, 4) === 'INFO') {
				$values = [...self::info(substr($list, 4)), ...$values];
			}

			// Chunks are padded to an even size.
			$at += 8 + $size + ($size % 2);
		}

		return new EmbeddedMetadata($values);
	}

	/**
	 * What a `fmt ` chunk says: its format tag, channels, sample rate,
	 * byte rate, block size, and bits a sample.
	 *
	 * @return array<string, mixed>
	 */
	private static function format(string $chunk): array
	{
		$bits = BinaryFile::uint16le($chunk, 14);

		return [
			'format'     => match (BinaryFile::uint16le($chunk)) {
				1, 0xFFFE => $bits > 0 ? "{$bits}-bit PCM" : 'PCM',
				3         => $bits > 0 ? "{$bits}-bit float" : 'Float',
				0x55      => 'MP3',
				default   => null
			},
			'channels'   => BinaryFile::uint16le($chunk, 2) ?: null,
			'sampleRate' => BinaryFile::uint32le($chunk, 4) ?: null,
			'bitrate'    => BinaryFile::uint32le($chunk, 8) * 8 ?: null
		];
	}

	/**
	 * An `INFO` list's text chunks.
	 *
	 * @return array<string, string>
	 */
	private static function info(string $list): array
	{
		$values = [];
		$at     = 0;

		while ($at + 8 <= strlen($list)) {
			$id    = substr($list, $at, 4);
			$size  = BinaryFile::uint32le($list, $at + 4);
			$text  = rtrim(substr($list, $at + 8, $size), "\0");
			$at   += 8 + $size + ($size % 2);

			if (isset(self::INFO[$id])) {
				$values[self::INFO[$id]] = self::INFO[$id] === 'created' ? EmbeddedMetadata::date($text) : $text;
			}
		}

		return $values;
	}
}
