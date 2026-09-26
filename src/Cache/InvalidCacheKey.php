<?php

/**
 * Invalid cache key.
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
 * Thrown for a key PSR-16 doesn't allow: empty, or holding one of
 * `{}()/\@:`.
 */
final class InvalidCacheKey extends InvalidArgumentException implements BlushException, PsrInvalidArgument
{
}
