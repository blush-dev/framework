<?php

/**
 * Process failed exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use RuntimeException;

/**
 * Thrown when an external process can't be started.
 */
final class ProcessFailed extends RuntimeException implements ConsoleException
{
}
