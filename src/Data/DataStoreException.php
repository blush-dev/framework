<?php

/**
 * Data store exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use RuntimeException;

/**
 * Thrown when a data store can't write, remove, or lock.
 */
final class DataStoreException extends RuntimeException implements DataException
{
}
