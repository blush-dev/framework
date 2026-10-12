<?php

/**
 * Invalid link attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * `Entry::link()` was given an attribute without a name (D-803).
 */
final class InvalidLinkAttribute extends InvalidArgumentException implements BlushException
{
}
