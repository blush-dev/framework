<?php

/**
 * Icon pack source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

/**
 * Where an icon pack was found (D-378).
 */
enum IconPackSource: string
{
	/** A folder in `extensions/`. */
	case Local = 'local';

	/** A Composer package of type `blush-icons`. */
	case Composer = 'composer';
}
