<?php

/**
 * Record store failure.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use RuntimeException;

/**
 * Thrown when a store can't write, remove, or lock: a disk or database
 * failing, not a record being wrong.
 */
final class RecordStoreFailure extends RuntimeException implements RecordException
{
}
