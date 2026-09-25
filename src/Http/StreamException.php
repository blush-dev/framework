<?php

/**
 * Stream exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use RuntimeException;

/**
 * Thrown when a stream can't be opened, read, written, or seeked, or has been
 * detached. PSR-7 requires `RuntimeException` for these.
 */
final class StreamException extends RuntimeException implements HttpException
{
}
