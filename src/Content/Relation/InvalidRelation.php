<?php

/**
 * Invalid relation exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use LogicException;
use Blush\Content\ContentException;

/**
 * Thrown for a relation that can't be defined as given, or relations
 * that don't fit together.
 */
final class InvalidRelation extends LogicException implements ContentException
{
}
