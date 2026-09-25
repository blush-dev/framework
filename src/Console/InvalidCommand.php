<?php

/**
 * Invalid command exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use LogicException;

/**
 * Thrown when a command class is declared incorrectly: no `#[Command]`
 * attribute, no public `__invoke()`, or an argument or option the console
 * can't parse. This is a developer error, not a user one.
 */
final class InvalidCommand extends LogicException implements ConsoleException
{
}
