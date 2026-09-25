<?php

/**
 * Invalid HTTP message value.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use InvalidArgumentException;

/**
 * Thrown when a URI, header, status code, or other message value is invalid.
 * PSR-7 requires `InvalidArgumentException` for these.
 */
final class InvalidMessage extends InvalidArgumentException implements HttpException
{
}
