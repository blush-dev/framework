<?php

/**
 * Preview layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

/**
 * The page shape the admin sketches a theme's preview in (D-381): one
 * column of text, text beside a sidebar, or a wide hero over a row of
 * cards.
 */
enum PreviewLayout: string
{
	case Centered = 'centered';
	case Sidebar  = 'sidebar';
	case Wide     = 'wide';
}
