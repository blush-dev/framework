<?php

/**
 * Media preload.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component\Media;

/**
 * How much of an `audio` or `video` file the browser loads before it's
 * played: the `preload` attribute.
 */
enum MediaPreload: string
{
	case None     = 'none';
	case Metadata = 'metadata';
	case Auto     = 'auto';
}
