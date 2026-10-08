<?php

/**
 * Invalid record.
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
 * Thrown when a record or a table can't be what it says: a malformed id
 * or key, a key another record has, or a stored table that can't be
 * read.
 */
final class InvalidRecord extends RuntimeException implements RecordException
{
}
