<?php

/**
 * Account suspended.
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
 * A sign-in with the right password for a suspended account (D-312).
 * It's only said once the password is right, so it tells a stranger
 * nothing.
 */
final class AccountSuspended extends RuntimeException implements BlushException
{
}
