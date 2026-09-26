<?php

/**
 * Cache exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use RuntimeException;
use Psr\SimpleCache\CacheException as PsrCacheException;
use Blush\Core\BlushException;

/**
 * Thrown when a cache store can't be built or used: an invalid namespace,
 * an unknown driver, or a driver whose PHP extension isn't loaded.
 */
final class CacheException extends RuntimeException implements BlushException, PsrCacheException
{
}
