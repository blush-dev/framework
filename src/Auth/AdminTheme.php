<?php

/**
 * Admin theme.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Which look an account sees the admin in (D-317): Neutral, cool gray
 * with a cobalt accent, or Editorial, warm paper with a teal accent and
 * serif titles. A per-account preference beside the color scheme, since
 * the admin's chrome isn't a property of the site.
 */
enum AdminTheme: string
{
	case Neutral   = 'neutral';
	case Editorial = 'editorial';
}
