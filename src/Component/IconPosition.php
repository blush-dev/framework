<?php

/**
 * Icon position.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * Which side of its text a component's icon goes on.
 */
enum IconPosition: string
{
	case Start = 'start';
	case End   = 'end';
}
