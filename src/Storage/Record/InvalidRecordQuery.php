<?php

/**
 * Invalid record query.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use InvalidArgumentException;

/**
 * Thrown when a record query is malformed: an empty key, a value an
 * operator can't take, or running a query no store made.
 */
final class InvalidRecordQuery extends InvalidArgumentException implements RecordException
{
}
