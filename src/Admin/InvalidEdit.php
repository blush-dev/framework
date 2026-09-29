<?php

/**
 * Invalid edit request.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * An editing request that doesn't make sense: a `set` that isn't an
 * object, an unknown status, scheduling without a future date. The API
 * answers it with a 400.
 */
final class InvalidEdit extends InvalidArgumentException implements BlushException
{
}
