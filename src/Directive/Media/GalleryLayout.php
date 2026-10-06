<?php

/**
 * Gallery layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Media;

/**
 * How a `gallery` lays out its images: in rows that grow to fill the
 * width (`flex`), or in even columns (`grid`).
 */
enum GalleryLayout: string
{
	case Flex = 'flex';
	case Grid = 'grid';
}
