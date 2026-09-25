<?php

/**
 * Unreadable source exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Source;

use RuntimeException;
use Blush\Content\ContentException;

/**
 * Thrown when a content document can't be listed or read, or when a path
 * would leave the source's root.
 */
final class UnreadableSource extends RuntimeException implements ContentException
{
}
