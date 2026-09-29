<?php

/**
 * Locked-out sign-in exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Too many failed sign-ins from an address (see `LoginThrottle`).
 */
final class LockedOut extends RuntimeException implements BlushException
{
}
