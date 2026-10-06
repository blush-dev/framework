<?php

/**
 * Embedded metadata.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * What a media file says about itself (D-238, D-289): values read from
 * its EXIF, IPTC, and XMP, under one set of keys whatever the format.
 * It's derived data: cached in the media index, never written to
 * `user/data`. The file's location (GPS) is kept apart, and never shown
 * or exported: an API says only whether there is one.
 *
 * - `title`, `description`, `creator`, `copyright`, `credit`: text.
 * - `keywords`: a list of text.
 * - `created`: when it was made, `YYYY-MM-DD HH:MM:SS`, with its offset
 *   when the file gives one.
 * - `camera`, `lens`, `focalLength` ("35 mm"), `aperture` ("f/2.8"),
 *   `exposure` ("1/250 s"), `iso`, `orientation` (EXIF's 1 to 8), and
 *   `software`.
 * - For sound and video (D-291): `album`, `track` ("3/12"), `genre`,
 *   `duration` (seconds), `artwork` (embedded cover art, described:
 *   "1400 × 1400 JPEG, 688 KB"; `ArtworkReader` gives the picture), and
 *   a video's `width` and `height`.
 * - What it's encoded as (D-551): `format` (a sound's codec, a video's
 *   picture codec, "MPEG-1 Layer III", "H.264", or a document's format,
 *   "PDF 1.7"); and of its sound, `bitrate` (bits a second),
 *   `sampleRate` (Hz), and `channels`; a video's `frameRate` (frames a
 *   second) and `audioFormat` (its sound's codec).
 * - For documents (D-551): `pages`, `pageSize` ("8.5 × 11 in (Letter)"),
 *   and `producer` (what wrote the PDF; `software` is what made it).
 */
final readonly class EmbeddedMetadata
{
	/**
	 * The keys, in the order they're shown.
	 *
	 * @var list<string>
	 */
	public const array KEYS = ['title', 'description', 'creator', 'album', 'track', 'genre', 'copyright', 'credit', 'keywords', 'created', 'duration', 'camera', 'lens', 'focalLength', 'aperture', 'exposure', 'iso', 'orientation', 'artwork', 'width', 'height', 'format', 'bitrate', 'sampleRate', 'channels', 'frameRate', 'audioFormat', 'pages', 'pageSize', 'software', 'producer'];

	/**
	 * The keys whose values are numbers with fractions, kept to the
	 * thousandth.
	 *
	 * @var list<string>
	 */
	private const array FRACTIONS = ['duration', 'frameRate'];

	/**
	 * The keys whose values are counts, kept only when there are some.
	 *
	 * @var list<string>
	 */
	private const array COUNTS = ['bitrate', 'sampleRate', 'channels', 'pages'];

	/**
	 * The values it has, by key, in `KEYS` order.
	 *
	 * @var array<string, string|int|float|list<string>>
	 */
	public array $values;

	/**
	 * @param array<string, mixed>              $values
	 * @param ?array{lat: float, lng: float} $location
	 */
	public function __construct(array $values = [], public ?array $location = null)
	{
		$kept = [];

		foreach (self::KEYS as $key) {
			$value = $values[$key] ?? null;

			if (is_string($value)) {
				$value = self::text($value);
			} elseif (is_array($value)) {
				$value = array_values(array_unique(array_filter(array_map(static fn (mixed $item): string => is_scalar($item) ? self::text((string) $item) : '', $value), static fn (string $item): bool => $item !== '')));
			} elseif (in_array($key, self::FRACTIONS, true) && (is_int($value) || is_float($value))) {
				// A duration, to the millisecond, or a frame rate.
				$value = $value > 0 && is_finite((float) $value) ? round((float) $value, 3) : '';
			} elseif (! is_int($value) || ($value <= 0 && in_array($key, self::COUNTS, true))) {
				continue;
			}

			if ($value !== '' && $value !== []) {
				$kept[$key] = $value;
			}
		}

		$this->values = $kept;
	}

	/**
	 * Returns a copy with another's values where this has none.
	 */
	public function merge(self $other): self
	{
		return new self([...$other->values, ...$this->values], $this->location ?? $other->location);
	}

	public function isEmpty(): bool
	{
		return $this->values === [] && $this->location === null;
	}

	/**
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$location = $data['location'] ?? null;
		$values   = [];

		foreach ($data as $key => $value) {
			$values[(string) $key] = $value;
		}

		return new self(
			$values,
			is_array($location) && is_float($location['lat'] ?? null) && is_float($location['lng'] ?? null) ? ['lat' => $location['lat'], 'lng' => $location['lng']] : null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [...$this->values, ...($this->location === null ? [] : ['location' => $this->location])];
	}

	/**
	 * A date as the file writes it (`2024:05:01 12:00:00`, `20240501`,
	 * `2024-05-01T12:00:00-05:00`) in one form: `YYYY-MM-DD HH:MM:SS`,
	 * then its offset if it has one; `''` when it isn't a date. A year,
	 * or a year and month, as sound files often have, stays as it is.
	 */
	public static function date(string $value, string $offset = ''): string
	{
		if (preg_match('/^\s*(\d{4})(?:-(\d{2}))?\s*$/', $value, $short) === 1) {
			return $short[1] === '0000' ? '' : $short[1] . (isset($short[2]) ? "-{$short[2]}" : '');
		}

		$match = preg_match('/^\s*(\d{4})[:\-]?(\d{2})[:\-]?(\d{2})(?:[T ]?(\d{2}):?(\d{2})(?::?(\d{2}))?(?:\.\d+)?)?\s*(Z|[+-]\d{2}:?\d{2})?/', $value, $parts);

		if ($match !== 1 || $parts[1] === '0000') {
			return '';
		}

		$time   = isset($parts[4]) && $parts[4] !== '' ? sprintf(' %s:%s:%s', $parts[4], $parts[5] ?? '00', ($parts[6] ?? '') !== '' ? $parts[6] : '00') : '';
		$offset = ($parts[7] ?? '') !== '' ? $parts[7] : trim($offset);

		if ($offset !== '' && $offset !== 'Z' && preg_match('/^([+-]\d{2}):?(\d{2})$/', $offset, $zone) === 1) {
			$offset = "{$zone[1]}:{$zone[2]}";
		}

		return sprintf('%s-%s-%s%s', $parts[1], $parts[2], $parts[3], $time) . ($offset === '' || $time === '' ? '' : " {$offset}");
	}

	/**
	 * Text as read: made UTF-8 (older files write Latin-1), without
	 * control characters or padding.
	 */
	public static function text(string $value): string
	{
		if (! mb_check_encoding($value, 'UTF-8')) {
			$value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
		}

		return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value));
	}
}
