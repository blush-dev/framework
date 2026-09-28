<?php

/**
 * Measure numbers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use NumberFormatter;

/**
 * Number formatting for the `progress` and `meter` components (D-188):
 * machine-readable attribute values, and text for people in the site's
 * locale.
 */
final readonly class MeasureNumbers
{
	public function __construct(private string $locale)
	{}

	/**
	 * Returns a number for an HTML attribute: plain decimal, no exponent
	 * or trailing zeros (`12`, `0.5`).
	 */
	public static function attribute(float $number): string
	{
		$text = rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');

		return $text === '-0' ? '0' : $text;
	}

	/**
	 * Returns a number for people (`1,250.5` in `en_US`), with up to two
	 * decimal places.
	 */
	public function number(float $number): string
	{
		$formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

		return $formatter->format($number) ?: self::attribute($number);
	}

	/**
	 * Returns a fraction as a percentage for people (`24%`), rounded to a
	 * whole number.
	 */
	public function percent(float $fraction): string
	{
		$formatter = new NumberFormatter($this->locale, NumberFormatter::PERCENT);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

		return $formatter->format($fraction) ?: round($fraction * 100) . '%';
	}
}
