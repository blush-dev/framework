<?php

/**
 * Placement of page markup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * Where a tag Blush adds to a page prints (D-578): in the `<head>`
 * (`$template->head()`), or at the end of the `<body>`
 * (`$template->foot()`).
 */
enum Placement: string
{
	case Head = 'head';
	case Foot = 'foot';
}
