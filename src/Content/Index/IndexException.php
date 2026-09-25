<?php

/**
 * Index exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use RuntimeException;
use Blush\Content\ContentException;

/**
 * Thrown when the content index can't be read or stored.
 */
final class IndexException extends RuntimeException implements ContentException
{
}
