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

namespace Blush\Block\Media;

/**
 * How a `gallery` lays out its images: in rows that grow to fill the
 * width (`flex`), in even columns (`grid`), or in columns that pack
 * images of any height (`masonry`, CSS grid lanes, D-725).
 */
enum GalleryLayout: string
{
	case Flex    = 'flex';
	case Grid    = 'grid';
	case Masonry = 'masonry';
}
