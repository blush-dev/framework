<?php

/**
 * Invalid HTML attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * A method printing a tag, such as `link()` or `img()`, was given an
 * attribute without a name (D-803, D-814).
 */
final class InvalidHtmlAttribute extends InvalidArgumentException implements BlushException
{
}
