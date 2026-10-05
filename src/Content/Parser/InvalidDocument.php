<?php

/**
 * Invalid document.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use RuntimeException;
use Blush\Content\ContentException;

/**
 * Thrown when a content document can't be parsed: malformed front matter,
 * or front matter that isn't a map.
 */
final class InvalidDocument extends RuntimeException implements ContentException
{
}
