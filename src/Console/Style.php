<?php

/**
 * Output style.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * ANSI SGR styles, each backed by its escape code.
 */
enum Style: int
{
	case Bold    = 1;
	case Dim     = 2;
	case Red     = 31;
	case Green   = 32;
	case Yellow  = 33;
	case Blue    = 34;
	case Magenta = 35;
	case Cyan    = 36;

	/**
	 * Wraps text in the escape codes for the given styles.
	 */
	public static function apply(string $text, self ...$styles): string
	{
		if ($styles === [] || $text === '') {
			return $text;
		}

		$codes = implode(';', array_map(static fn (self $style): int => $style->value, $styles));

		return "\033[{$codes}m{$text}\033[0m";
	}
}
