<?php

/**
 * Invalid schema.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

use LogicException;
use Blush\Content\ContentException;

/**
 * Thrown when a schema or field is badly defined: an unknown field type, a
 * missing name, or a name or alias used twice.
 */
final class InvalidSchema extends LogicException implements ContentException
{
}
