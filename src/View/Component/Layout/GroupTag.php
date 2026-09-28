<?php

/**
 * Group tag.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component\Layout;

/**
 * The element a `group` renders as.
 */
enum GroupTag: string
{
	case Div     = 'div';
	case Section = 'section';
}
