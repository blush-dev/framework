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
 * comment, copyright, encoder, and cover art, described, and given by
 * `artwork()`). Each track's media (`mdia`, D-551) says what it is
 * (`hdlr`) and its codec (`stsd`): a video's frame rate is its samples
 * (`stts`) over its length (`mdhd`); a sound's channels and sample rate
 * are in its sample entry, and its bit rate is its samples' sizes
 * (`stsz`) over its length. A fragmented file (`mvex`, as streaming
 * and browser recordings write) keeps its samples in fragments after
 * the index (`moof`), whose runs (`trun`) are counted instead, with
 * their tracks' defaults (`trex`, `tfhd`). The media data (`mdat`) is
 * skipped, never read, wherever it is.
 *
 * @phpstan-type Track array{id: int, handler: string, width: int, height: int, codec: ?string, scale: int, seconds: float, samples: int, bytes: int, channels: int, rate: int}
 */
final readonly class Mp4Reader implements ArtworkReader
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
	 * Codecs by sample entry type.
	 *
	 * @var array<string, string>
	 */
	private const array CODECS = [
		'avc1' => 'H.264',
		'avc3' => 'H.264',
		'hvc1' => 'HEVC',
		'hev1' => 'HEVC',
		'vp08' => 'VP8',
		'vp09' => 'VP9',
		'av01' => 'AV1',
		'mp4v' => 'MPEG-4 Visual',
		'apch' => 'ProRes',
		'apcn' => 'ProRes',
		'apcs' => 'ProRes',
		'apco' => 'ProRes',
		'ap4h' => 'ProRes',
		'mp4a' => 'AAC',
		'alac' => 'ALAC',
		'ac-3' => 'AC-3',
		'ec-3' => 'E-AC-3',
		'Opus' => 'Opus',
		'fLaC' => 'FLAC',
		'.mp3' => 'MP3',
		'lpcm' => 'PCM',
		'sowt' => 'PCM',
		'twos' => 'PCM'
	];

	private const array MIMES = ['video/mp4', 'audio/mp4', 'audio/x-m4a', 'video/quicktime'];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		$file = new BinaryFile($path);
		$moov = in_array($mime, self::MIMES, true) ? self::moov($file) : null;

		return new EmbeddedMetadata($moov === null ? [] : self::movie($moov, $file));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function artwork(string $path, string $mime): ?Artwork
	{
		$moov = in_array($mime, self::MIMES, true) ? self::moov(new BinaryFile($path)) : null;

		foreach ($moov === null ? [] : self::children($moov) as [$type, $box]) {
			$ilst = match ($type) {
				'udta'  => self::ilst($box),
				'meta'  => self::ilst($box, true),
				default => null
			};

			foreach ($ilst === null ? [] : self::children($ilst) as [$item, $data]) {
				if ($item === 'covr') {
					return self::data($data) === null ? null : Artwork::from((string) self::data($data));
				}
			}
		}

		return null;
	}

	/**
	 * The file's `moov` box's contents, or `null`.
	 */
	private static function moov(BinaryFile $file): ?string
	{
		foreach (self::boxes($file, 0, $file->size) as [$type, $start, $end]) {
			if ($type === 'moov') {
				return $file->read($start, min($end - $start, self::LIMIT));
			}
		}

		return null;
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
	 * What `moov` says: its duration and tags, and its first video and
	 * sound tracks. A video's codec is its `format` and its sound's its
	 * `audioFormat`; a sound alone's is its `format`.
	 *
	 * @return array<string, mixed>
	 */
	private static function movie(string $moov, BinaryFile $file): array
	{
		$values   = [];
		$tracks   = [];
		$defaults = null;

		foreach (self::children($moov) as [$type, $box]) {
			if ($type === 'mvhd') {
				$long  = ord($box[0] ?? "\0") === 1;
				$scale = BinaryFile::uint32be($box, $long ? 20 : 12);
				$span  = $long ? BinaryFile::uint64be($box, 24) : BinaryFile::uint32be($box, 16);

				$values['duration'] = $scale === 0 ? null : $span / $scale;
			} elseif ($type === 'trak') {
				$tracks[] = self::track($box);
			} elseif ($type === 'mvex') {
				$defaults = self::defaults($box);
			} elseif ($type === 'udta') {
				$values = [...self::items(self::ilst($box) ?? ''), ...$values];
			} elseif ($type === 'meta') {
				$values = [...self::items(self::ilst($box, true) ?? ''), ...$values];
			}
		}

		if ($defaults !== null) {
			$tracks = self::fragmented($file, $tracks, $defaults);

			$values['duration'] = ($values['duration'] ?? 0) ?: max([0, ...array_column($tracks, 'seconds')]);
		}

		$video = array_find($tracks, static fn (array $track): bool => $track['handler'] === 'vide');
		$sound = array_find($tracks, static fn (array $track): bool => $track['handler'] === 'soun');
		$sized = $video ?? array_find($tracks, static fn (array $track): bool => $track['width'] > 0);

		if ($sized !== null && $sized['width'] > 0 && $sized['height'] > 0) {
			$values['width']  = $sized['width'];
			$values['height'] = $sized['height'];
		}

		if ($video !== null) {
			$values['format']    = $video['codec'];
			$values['frameRate'] = $video['seconds'] > 0 ? $video['samples'] / $video['seconds'] : null;
		}

		if ($sound !== null) {
			$values[$video === null ? 'format' : 'audioFormat'] = $sound['codec'];
			$values['bitrate']    = $sound['seconds'] > 0 && $sound['bytes'] > 0 ? (int) round($sound['bytes'] * 8 / $sound['seconds']) : null;
			$values['sampleRate'] = $sound['rate'] > 0 ? $sound['rate'] : null;
			$values['channels']   = $sound['channels'] > 0 ? $sound['channels'] : null;
		}

		return $values;
	}

	/**
	 * A track: its ID and size (`tkhd`, 16.16 fixed point), and from its media,
	 * what it is, its codec, time scale, length, sample count, and sample sizes, and
	 * a sound's channels and sample rate.
	 *
	 * @return Track
	 */
	private static function track(string $trak): array
	{
		$track = ['id' => 0, 'handler' => '', 'width' => 0, 'height' => 0, 'codec' => null, 'scale' => 0, 'seconds' => 0.0, 'samples' => 0, 'bytes' => 0, 'channels' => 0, 'rate' => 0];

		foreach (self::children($trak) as [$type, $box]) {
			if ($type === 'tkhd') {
				$long            = ord($box[0] ?? "\0") === 1;
				$track['id']     = BinaryFile::uint32be($box, $long ? 20 : 12);
				$offset          = $long ? 88 : 76;
				$track['width']  = BinaryFile::uint32be($box, $offset) >> 16;
				$track['height'] = BinaryFile::uint32be($box, $offset + 4) >> 16;
			} elseif ($type === 'mdia') {
				$track = self::media($box, $track);
			}
		}

		return $track;
	}

	/**
	 * What a track's `mdia` says, added to the track.
	 *
	 * @param  Track $track
	 * @return Track
	 */
	private static function media(string $mdia, array $track): array
	{
		$stbl = '';

		foreach (self::children($mdia) as [$type, $box]) {
			if ($type === 'mdhd') {
				$long  = ord($box[0] ?? "\0") === 1;
				$scale = BinaryFile::uint32be($box, $long ? 20 : 12);
				$span  = $long ? BinaryFile::uint64be($box, 24) : BinaryFile::uint32be($box, 16);

				$track['scale']   = $scale;
				$track['seconds'] = $scale === 0 ? 0.0 : $span / $scale;
			} elseif ($type === 'hdlr') {
				$track['handler'] = substr($box, 8, 4);
			} elseif ($type === 'minf') {
				foreach (self::children($box) as [$child, $table]) {
					$stbl = $child === 'stbl' ? $table : $stbl;
				}
			}
		}

		foreach (self::children($stbl) as [$type, $box]) {
			if ($type === 'stsd' && strlen($box) >= 16) {
				// A full box and an entry count, then the first entry:
				// its size, type, six reserved bytes, and a reference
				// index; a sound's goes on with a version, revision,
				// vendor, channels, sample size, compression, packet
				// size, and its rate, 16.16.
				$entry          = substr($box, 12, 4);
				$track['codec'] = self::CODECS[$entry] ?? (trim($entry) === '' ? null : trim($entry));

				if ($track['handler'] === 'soun') {
					$track['channels'] = BinaryFile::uint16be($box, 32);
					$track['rate']     = BinaryFile::uint32be($box, 40) >> 16;
				}
			} elseif ($type === 'stts') {
				$count = BinaryFile::uint32be($box, 4);

				for ($i = 0; $i < $count && 8 + $i * 8 + 4 <= strlen($box); $i++) {
					$track['samples'] += BinaryFile::uint32be($box, 8 + $i * 8);
				}
			} elseif ($type === 'stsz' && $track['handler'] === 'soun') {
				$size  = BinaryFile::uint32be($box, 4);
				$count = BinaryFile::uint32be($box, 8);

				if ($size > 0) {
					$track['bytes'] = $size * $count;
				} else {
					for ($i = 0; $i < $count && 12 + $i * 4 + 4 <= strlen($box); $i++) {
						$track['bytes'] += BinaryFile::uint32be($box, 12 + $i * 4);
					}
				}
			}
		}

		return $track;
	}

	/**
	 * Each track's sample defaults in a fragmented file (`mvex/trex`):
	 * duration and size, by track ID.
	 *
	 * @return array<int, array{int, int}>
	 */
	private static function defaults(string $mvex): array
	{
		$defaults = [];

		foreach (self::children($mvex) as [$type, $box]) {
			if ($type === 'trex') {
				$defaults[BinaryFile::uint32be($box, 4)] = [BinaryFile::uint32be($box, 12), BinaryFile::uint32be($box, 16)];
			}
		}

		return $defaults;
	}

	/**
	 * The tracks with what a fragmented file's fragments say added to
	 * what its index does: each
	 * run's samples, their durations (the run's own, else its fragment's
	 * default, else the track's), and their sizes, the same way. The
	 * most fragments read is 100,000.
	 *
	 * @param  list<Track>                $tracks
	 * @param  array<int, array{int, int}> $defaults
	 * @return list<Track>
	 */
	private static function fragmented(BinaryFile $file, array $tracks, array $defaults): array
	{
		$spans = [];
		$read  = 0;

		foreach (self::boxes($file, 0, $file->size) as [$type, $start, $end]) {
			if ($type !== 'moof' || ++$read > 100_000) {
				continue;
			}

			foreach (self::children($file->read($start, min($end - $start, self::LIMIT))) as [$child, $traf]) {
				if ($child !== 'traf') {
					continue;
				}

				$id       = 0;
				$duration = 0;
				$size     = 0;

				foreach (self::children($traf) as [$part, $box]) {
					if ($part === 'tfhd') {
						$flags    = BinaryFile::uintbe(substr($box, 1, 3));
						$id       = BinaryFile::uint32be($box, 4);
						$at       = 8 + (($flags & 0x1) !== 0 ? 8 : 0) + (($flags & 0x2) !== 0 ? 4 : 0);
						$duration = ($flags & 0x8) !== 0 ? BinaryFile::uint32be($box, $at) : ($defaults[$id][0] ?? 0);
						$at      += ($flags & 0x8) !== 0 ? 4 : 0;
						$size     = ($flags & 0x10) !== 0 ? BinaryFile::uint32be($box, $at) : ($defaults[$id][1] ?? 0);
					} elseif ($part === 'trun') {
						$flags  = BinaryFile::uintbe(substr($box, 1, 3));
						$count  = BinaryFile::uint32be($box, 4);
						$at     = 8 + (($flags & 0x1) !== 0 ? 4 : 0) + (($flags & 0x4) !== 0 ? 4 : 0);
						$each   = 4 * count(array_filter([0x100, 0x200, 0x400, 0x800], static fn (int $flag): bool => ($flags & $flag) !== 0));
						$span   = $spans[$id] ?? [0, 0, 0];

						$span[0] += $count;

						for ($i = 0; $i < $count; $i++) {
							$sample   = $at + $i * $each;
							$span[1] += ($flags & 0x100) !== 0 ? BinaryFile::uint32be($box, $sample) : $duration;
							$span[2] += ($flags & 0x200) !== 0 ? BinaryFile::uint32be($box, $sample + (($flags & 0x100) !== 0 ? 4 : 0)) : $size;
						}

						$spans[$id] = $span;
					}
				}
			}
		}

		return array_map(static function (array $track) use ($spans): array {
			[$samples, $ticks, $bytes] = $spans[$track['id']] ?? [0, 0, 0];

			if ($samples > 0 && $track['scale'] > 0) {
				$track['samples'] += $samples;
				$track['seconds'] += $ticks / $track['scale'];
				$track['bytes']   += $bytes;
			}

			return $track;
		}, $tracks);
	}

	/**
	 * The `ilst` in `udta/meta` (or `meta` straight under `moov`), or
	 * `null`. A `meta` box is a full box, with four bytes of version and
	 * flags before its children, in MP4; QuickTime's has none.
	 */
	private static function ilst(string $box, bool $isMeta = false): ?string
	{
		foreach ($isMeta ? [['meta', $box]] : self::children($box) as [$type, $meta]) {
			if ($type !== 'meta') {
				continue;
			}

			$meta = substr($meta, 4, 4) === 'hdlr' ? $meta : substr($meta, 4);

			foreach (self::children($meta) as [$child, $ilst]) {
				if ($child === 'ilst') {
					return $ilst;
				}
			}
		}

		return null;
	}

	/**
	 * An `ilst` item's value: its `data` box, after a type and locale.
	 */
	private static function data(string $item): ?string
	{
		foreach (self::children($item) as [$child, $box]) {
			if ($child === 'data') {
				return substr($box, 8);
			}
		}

		return null;
	}

	/**
	 * `ilst` items.
	 *
	 * @return array<string, mixed>
	 */
	private static function items(string $ilst): array
	{
		$values = [];

		foreach (self::children($ilst) as [$type, $item]) {
			$data = self::data($item);

			if ($data === null) {
				continue;
			}

			if ($type === 'trkn' && strlen($data) >= 6) {
				$number = BinaryFile::uint16be($data, 2);
				$total  = BinaryFile::uint16be($data, 4);

				$values['track'] = $number === 0 ? null : ($total === 0 ? (string) $number : "{$number}/{$total}");
			} elseif ($type === 'covr') {
				$values['artwork'] = Artwork::from($data)?->describe();
			} elseif (isset(self::ITEMS[$type])) {
				$key          = self::ITEMS[$type];
				$values[$key] = $key === 'created' ? EmbeddedMetadata::date($data) : $data;
			}
		}

		return $values;
	}
}
