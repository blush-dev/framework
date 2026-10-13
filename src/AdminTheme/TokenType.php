<?php

/**
 * Token type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

/**
 * What an admin theme's token holds (D-827), which says how its value is
 * written and checked.
 */
enum TokenType: string
{
	/** `light-dark(light, dark)`, or one color for both schemes. */
	case Color = 'color';

	/** Pixels, within the token's range (`"8px"`, `"13.5px"`, `"0"`). */
	case Length = 'length';

	/** A list of font family names, ending in a generic one or not. */
	case FontFamily = 'font-family';

	/** A weight, within the token's range (`500`). */
	case FontWeight = 'font-weight';

	/** Ems, within the token's range (`"-.014em"`, `"0"`). */
	case LetterSpacing = 'letter-spacing';
}
