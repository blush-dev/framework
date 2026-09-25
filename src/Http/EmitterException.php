<?php

/**
 * Emitter exception.
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
 * Thrown when a response can't be emitted because output has already started.
 */
final class EmitterException extends RuntimeException implements HttpException
{
}
