<?php

/**
 * Invalid cache value.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use InvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgument;
use Blush\Core\BlushException;

/**
 * Thrown when a value to cache isn't plain data (it holds an object or a
 * resource).
 */
final class InvalidCacheValue extends InvalidArgumentException implements BlushException, PsrInvalidArgument
{
}
