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
 * Thrown when a content file can't be parsed: malformed front matter, front
 * matter that isn't a map, or an extension no parser handles.
 */
final class InvalidDocument extends RuntimeException implements ContentException
{
}
