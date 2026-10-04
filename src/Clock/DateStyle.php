<?php

/**
 * Date style enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use IntlDateFormatter;

/**
 * The date and time styles a language defines (D-447), by the name a
 * format is written with: `$template->date($date, DateStyle::Short)` is
 * `$template->date($date, 'short')`. Each one's order, words, and
 * punctuation follow the site's language (`Long` is "October 4, 2026" in
 * `en_US`, "4. Oktober 2026" in `de_DE`).
 */
enum DateStyle: string
{
	case Full   = 'full';
	case Long   = 'long';
	case Medium = 'medium';
	case Short  = 'short';

	/**
	 * Its `IntlDateFormatter` constant.
	 */
	public function icu(): int
	{
		return match ($this) {
			self::Full   => IntlDateFormatter::FULL,
			self::Long   => IntlDateFormatter::LONG,
			self::Medium => IntlDateFormatter::MEDIUM,
			self::Short  => IntlDateFormatter::SHORT
		};
	}
}
