<?php

/**
 * CSS color.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

/**
 * One color an admin theme's `tokens.json` gives (D-827), in the forms
 * Blush reads: hex (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`), `rgb()`,
 * `hsl()`, and `oklch()` (with `rgba()` and `hsla()`, comma or space
 * separated, an alpha after `/`), and `transparent`. Anything else isn't
 * one, so what Blush writes into a stylesheet is only ever a color.
 *
 * Each is also read into sRGB, for the contrast checks.
 */
final readonly class CssColor
{
	/**
	 * A number, a percentage, or an angle in degrees.
	 */
	private const string NUMBER = '/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(%|deg)?$/';

	/**
	 * @param string $css   The color as Blush writes it.
	 * @param float  $red   0 to 1, in sRGB.
	 * @param float  $green 0 to 1, in sRGB.
	 * @param float  $blue  0 to 1, in sRGB.
	 * @param float  $alpha 0 to 1.
	 */
	private function __construct(
		public string $css,
		public float $red,
		public float $green,
		public float $blue,
		public float $alpha
	) {}

	/**
	 * Reads a color, or returns `null` when it isn't one Blush reads.
	 */
	public static function parse(string $value): ?self
	{
		$value = strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));

		if ($value === 'transparent') {
			return new self($value, 0.0, 0.0, 0.0, 0.0);
		}

		if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $match) === 1) {
			return self::hex($value, $match[1]);
		}

		if (preg_match('/^(rgba?|hsla?|oklch) ?\((.*)\)$/', $value, $match) !== 1) {
			return null;
		}

		$arguments = self::arguments($match[2]);

		if ($arguments === null) {
			return null;
		}

		[$channels, $alpha] = $arguments;
		$alpha              = $alpha === null ? 1.0 : self::fraction($alpha, 1.0);

		if ($alpha === null) {
			return null;
		}

		$css = sprintf('%s(%s%s)', $match[1], implode(' ', $channels), $arguments[1] === null ? '' : " / {$arguments[1]}");

		$rgb = match ($match[1]) {
			'rgb', 'rgba' => self::rgb($channels),
			'hsl', 'hsla' => self::hsl($channels),
			default       => self::oklch($channels)
		};

		return $rgb === null ? null : new self($css, ...[...$rgb, $alpha]);
	}

	/**
	 * Returns its relative luminance, as WCAG works it out (alpha left
	 * out).
	 */
	public function luminance(): float
	{
		$linear = static fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;

		return 0.2126 * $linear($this->red) + 0.7152 * $linear($this->green) + 0.0722 * $linear($this->blue);
	}

	/**
	 * Returns its contrast ratio with another color, 1 to 21.
	 */
	public function contrast(self $other): float
	{
		$lighter = max($this->luminance(), $other->luminance());
		$darker  = min($this->luminance(), $other->luminance());

		return ($lighter + 0.05) / ($darker + 0.05);
	}

	/**
	 * Reads a hex color's digits.
	 */
	private static function hex(string $css, string $digits): self
	{
		if (strlen($digits) <= 4) {
			$digits = implode('', array_map(static fn (string $digit): string => $digit . $digit, str_split($digits)));
		}

		$channels = array_map(static fn (string $pair): float => hexdec($pair) / 255, str_split($digits, 2));

		return new self($css, $channels[0], $channels[1], $channels[2], $channels[3] ?? 1.0);
	}

	/**
	 * Splits a function's arguments into its three channels and its
	 * alpha, either comma separated or space separated with the alpha
	 * after a `/`, each a number, a percentage, or an angle; `null` when
	 * they aren't.
	 *
	 * @return ?array{list<string>, ?string}
	 */
	private static function arguments(string $arguments): ?array
	{
		if (str_contains($arguments, ',')) {
			$parts = array_map(trim(...), explode(',', $arguments));
			$alpha = count($parts) === 4 ? array_pop($parts) : null;
		} else {
			$halves = array_map(trim(...), explode('/', $arguments));

			if (count($halves) > 2) {
				return null;
			}

			$parts = explode(' ', $halves[0]);
			$alpha = $halves[1] ?? null;
		}

		$all = $alpha === null ? $parts : [...$parts, $alpha];

		if (count($parts) !== 3 || ! array_all($all, static fn (string $part): bool => preg_match(self::NUMBER, $part) === 1)) {
			return null;
		}

		return [$parts, $alpha];
	}

	/**
	 * Reads a number or a percentage as a share of `$whole`, clamped to
	 * 0 to 1; `null` for an angle.
	 */
	private static function fraction(string $value, float $whole): ?float
	{
		if (str_ends_with($value, 'deg')) {
			return null;
		}

		$number = str_ends_with($value, '%') ? (float) $value / 100 : (float) $value / $whole;

		return max(0.0, min(1.0, $number));
	}

	/**
	 * Reads an angle in degrees, a plain number being degrees too.
	 */
	private static function hue(string $value): ?float
	{
		if (str_ends_with($value, '%')) {
			return null;
		}

		return fmod(fmod((float) $value, 360) + 360, 360);
	}

	/**
	 * Reads `rgb()`'s channels.
	 *
	 * @param  list<string>               $channels
	 * @return ?array{float, float, float}
	 */
	private static function rgb(array $channels): ?array
	{
		$rgb = array_map(static fn (string $channel): ?float => self::fraction($channel, 255), $channels);

		return in_array(null, $rgb, true) ? null : [(float) $rgb[0], (float) $rgb[1], (float) $rgb[2]];
	}

	/**
	 * Reads `hsl()`'s channels: a hue, then saturation and lightness as
	 * percentages (or numbers out of 100).
	 *
	 * @param  list<string>               $channels
	 * @return ?array{float, float, float}
	 */
	private static function hsl(array $channels): ?array
	{
		$hue        = self::hue($channels[0]);
		$saturation = self::fraction($channels[1], 100);
		$lightness  = self::fraction($channels[2], 100);

		if ($hue === null || $saturation === null || $lightness === null) {
			return null;
		}

		$chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
		$at     = static function (float $offset) use ($hue, $lightness, $chroma): float {
			$k = fmod($offset + $hue / 30, 12);

			return $lightness - $chroma / 2 * max(-1, min($k - 3, 9 - $k, 1));
		};

		return [$at(0), $at(8), $at(4)];
	}

	/**
	 * Reads `oklch()`'s channels (lightness as a number from 0 to 1 or a
	 * percentage, chroma as a number or a percentage of 0.4, and a hue)
	 * into sRGB, clamped to its gamut.
	 *
	 * @param  list<string>               $channels
	 * @return ?array{float, float, float}
	 */
	private static function oklch(array $channels): ?array
	{
		$lightness = self::fraction($channels[0], 1);
		$hue       = self::hue($channels[2]);

		if ($lightness === null || $hue === null || str_ends_with($channels[1], 'deg')) {
			return null;
		}

		$chroma = str_ends_with($channels[1], '%') ? (float) $channels[1] / 100 * 0.4 : max(0.0, (float) $channels[1]);
		$a      = $chroma * cos(deg2rad($hue));
		$b      = $chroma * sin(deg2rad($hue));

		$l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
		$m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
		$s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

		$encode = static function (float $linear): float {
			$linear = max(0.0, min(1.0, $linear));

			return $linear <= 0.0031308 ? 12.92 * $linear : 1.055 * $linear ** (1 / 2.4) - 0.055;
		};

		return [
			$encode(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
			$encode(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
			$encode(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s)
		];
	}
}
