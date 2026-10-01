<?php

/**
 * EXIF reader.
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
 * Reads EXIF (D-289) from JPEG, TIFF, and the other formats PHP's `exif`
 * extension reads: the camera, lens, and exposure, when it was taken, the
 * description, artist, copyright, and software, and the GPS location.
 * Without the extension it reads nothing.
 */
final readonly class ExifReader implements EmbeddedReader
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if (! function_exists('exif_read_data') || ! in_array($mime, ['image/jpeg', 'image/tiff', 'image/webp', 'image/heic', 'image/heif', 'image/avif'], true)) {
			return new EmbeddedMetadata();
		}

		$data = @exif_read_data($path, null, true);

		if (! is_array($data)) {
			return new EmbeddedMetadata();
		}

		$ifd  = is_array($data['IFD0'] ?? null) ? $data['IFD0'] : [];
		$exif = is_array($data['EXIF'] ?? null) ? $data['EXIF'] : [];
		$gps  = is_array($data['GPS'] ?? null) ? $data['GPS'] : [];

		$make  = self::string($ifd['Make'] ?? null);
		$model = self::string($ifd['Model'] ?? null);

		// A model often starts with its maker ("Canon Canon EOS R5").
		$camera = $make !== '' && str_starts_with(strtolower($model), strtolower(strtok($make, ' ') ?: $make)) ? $model : trim("{$make} {$model}");

		$focal    = self::number($exif['FocalLength'] ?? null);
		$aperture = self::number($exif['FNumber'] ?? null);
		$iso      = $exif['ISOSpeedRatings'] ?? $exif['PhotographicSensitivity'] ?? null;
		$iso      = is_array($iso) ? reset($iso) : $iso;

		return new EmbeddedMetadata([
			'description' => self::string($ifd['ImageDescription'] ?? null),
			'creator'     => self::string($ifd['Artist'] ?? null),
			'copyright'   => self::string($ifd['Copyright'] ?? null),
			'created'     => EmbeddedMetadata::date(self::string($exif['DateTimeOriginal'] ?? $ifd['DateTime'] ?? null), self::string($exif['OffsetTimeOriginal'] ?? null)),
			'camera'      => $camera,
			'lens'        => self::string($exif['LensModel'] ?? $exif['UndefinedTag:0xA434'] ?? null),
			'focalLength' => $focal === null ? '' : self::trimmed($focal) . ' mm',
			'aperture'    => $aperture === null ? '' : 'f/' . self::trimmed($aperture),
			'exposure'    => self::exposure($exif['ExposureTime'] ?? null),
			'iso'         => is_numeric($iso) ? (int) $iso : null,
			'orientation' => is_numeric($ifd['Orientation'] ?? null) ? (int) $ifd['Orientation'] : null,
			'software'    => self::string($ifd['Software'] ?? null)
		], self::location($gps));
	}

	private static function string(mixed $value): string
	{
		return is_string($value) ? EmbeddedMetadata::text($value) : '';
	}

	/**
	 * A number EXIF writes as a rational (`28/10`) or a number.
	 */
	private static function number(mixed $value): ?float
	{
		if (is_int($value) || is_float($value)) {
			return (float) $value;
		}

		if (is_string($value) && preg_match('#^\s*(-?\d+(?:\.\d+)?)(?:\s*/\s*(\d+(?:\.\d+)?))?\s*$#', $value, $parts) === 1) {
			$divisor = isset($parts[2]) ? (float) $parts[2] : 1.0;

			return $divisor == 0.0 ? null : (float) $parts[1] / $divisor;
		}

		return null;
	}

	private static function trimmed(float $value): string
	{
		return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
	}

	/**
	 * An exposure time as a photographer says it: `1/250 s`, or `2 s`.
	 */
	private static function exposure(mixed $value): string
	{
		$seconds = self::number($value);

		if ($seconds === null || $seconds <= 0) {
			return '';
		}

		return $seconds < 1 ? sprintf('1/%d s', (int) round(1 / $seconds)) : self::trimmed($seconds) . ' s';
	}

	/**
	 * The GPS location, in decimal degrees.
	 *
	 * @param  array<array-key, mixed>          $gps
	 * @return ?array{lat: float, lng: float}
	 */
	private static function location(array $gps): ?array
	{
		$lat = self::degrees($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? null);
		$lng = self::degrees($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? null);

		return $lat === null || $lng === null ? null : ['lat' => $lat, 'lng' => $lng];
	}

	private static function degrees(mixed $parts, mixed $reference): ?float
	{
		if (! is_array($parts) || count($parts) !== 3) {
			return null;
		}

		$values = array_map(self::number(...), array_values($parts));

		if (in_array(null, $values, true)) {
			return null;
		}

		$degrees = (float) $values[0] + (float) $values[1] / 60 + (float) $values[2] / 3600;

		return round(in_array($reference, ['S', 'W'], true) ? -$degrees : $degrees, 6);
	}
}
