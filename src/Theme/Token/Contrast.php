<?php

/**
 * Color contrast.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme\Token;

/**
 * WCAG 2.2 contrast ratios between token colors, for `theme:check`
 * (D-030). Reads `#rgb`, `#rrggbb` (alpha ignored), and `rgb()`/`rgba()`
 * with 0–255 channels; other color syntaxes can't be measured.
 */
final class Contrast
{
	/**
	 * The AA minimum for body text.
	 */
	public const float AA = 4.5;

	/**
	 * Returns the contrast ratio between two colors (1 to 21), or `null`
	 * when either can't be read.
	 */
	public static function ratio(string $foreground, string $background): ?float
	{
		$a = self::luminance($foreground);
		$b = self::luminance($background);

		if ($a === null || $b === null) {
			return null;
		}

		return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
	}

	/**
	 * Returns a color's relative luminance, or `null`.
	 */
	private static function luminance(string $color): ?float
	{
		$rgb = self::rgb(trim(strtolower($color)));

		if ($rgb === null) {
			return null;
		}

		[$r, $g, $b] = array_map(static function (int $channel): float {
			$value = $channel / 255;

			return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
		}, $rgb);

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * Returns a color's channels, or `null`.
	 *
	 * @return ?array{int, int, int}
	 */
	private static function rgb(string $color): ?array
	{
		if (preg_match('/^#([0-9a-f]{3,4})$/', $color, $match) === 1) {
			return [(int) hexdec($match[1][0] . $match[1][0]), (int) hexdec($match[1][1] . $match[1][1]), (int) hexdec($match[1][2] . $match[1][2])];
		}

		if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})(?:[0-9a-f]{2})?$/', $color, $match) === 1) {
			return [(int) hexdec($match[1]), (int) hexdec($match[2]), (int) hexdec($match[3])];
		}

		if (preg_match('/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $color, $match) === 1) {
			return [min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3])];
		}

		return null;
	}
}
