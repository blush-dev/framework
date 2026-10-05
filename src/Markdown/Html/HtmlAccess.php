<?php

/**
 * HTML access enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Html;

/**
 * How much raw HTML someone may add to a body in the admin (D-495): none,
 * the allowed list's (`html.allowed`), or anything but what's always
 * refused (`html.unfiltered`). Unsafe link addresses are refused at
 * every level.
 */
enum HtmlAccess
{
	case None;
	case Allowed;
	case Unfiltered;
}
