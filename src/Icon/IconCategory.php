<?php

/**
 * Icon categories.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

/**
 * The groups the admin's icon picker shows the core icons in (D-265),
 * each core icon in one, as `resources/icons/blush/categories.json` has
 * it. A theme's, the site's, or an extension's icons are grouped by where
 * they come from instead.
 */
enum IconCategory: string
{
	case Arrows        = 'arrows';
	case Interface     = 'interface';
	case Status        = 'status';
	case Communication = 'communication';
	case People        = 'people';
	case Security      = 'security';
	case Writing       = 'writing';
	case Media         = 'media';
	case Development   = 'development';
	case Design        = 'design';
	case Time          = 'time';
	case Places        = 'places';
	case Nature        = 'nature';
	case Things        = 'things';
}
