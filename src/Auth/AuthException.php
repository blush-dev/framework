<?php

/**
 * Auth exception.
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
 * An account, role, or password that can't be used: an invalid username,
 * an unknown role, a short password, or a damaged account file.
 */
final class AuthException extends RuntimeException implements BlushException
{
}
