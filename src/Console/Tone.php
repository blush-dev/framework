<?php

/**
 * Output tone.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * What a piece of output is, so `Output` decides how it looks (D-717):
 * green, yellow, and red only for results, one accent for what can be
 * typed, dim for what's secondary, and bold for headings. Everything
 * else is left in the terminal's own color. `Palette` says which colors
 * draw each one.
 */
enum Tone
{
	case Code;
	case Heading;
	case Muted;
	case Pass;
	case Warning;
	case Failure;
}
