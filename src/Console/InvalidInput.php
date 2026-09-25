<?php

/**
 * Invalid input exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use InvalidArgumentException;

/**
 * Thrown when a command line doesn't match a command's signature: an unknown
 * option, a missing argument, a value of the wrong type, and so on. The
 * console reports it with the command's usage and exits with
 * `ExitCode::Invalid`.
 */
final class InvalidInput extends InvalidArgumentException implements ConsoleException
{
}
