<?php

/**
 * Invalid field value.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

use InvalidArgumentException;
use Blush\Content\ContentException;

/**
 * Thrown by a field when a value doesn't fit it. `Schema` catches it and
 * reports a violation, so one bad value never stops an entry from being
 * indexed.
 */
final class InvalidField extends InvalidArgumentException implements ContentException
{
}
