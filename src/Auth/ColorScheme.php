<?php

/**
 * Admin color scheme.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Whether an account sees the admin light, dark, or as its system is set
 * (D-235).
 */
enum ColorScheme: string
{
	case System = 'system';
	case Light  = 'light';
	case Dark   = 'dark';
}
