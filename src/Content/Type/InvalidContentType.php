<?php

/**
 * Invalid content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use LogicException;
use Blush\Content\ContentException;

/**
 * Thrown when a content type is badly defined, or when the types don't fit
 * together: two types with one name or path, a data-defined type that a
 * developer type already defines (D-042), or a reference to a type that
 * doesn't exist.
 */
final class InvalidContentType extends LogicException implements ContentException
{
}
