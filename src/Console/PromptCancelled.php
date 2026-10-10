<?php

/**
 * Prompt cancelled.
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
 * Thrown when someone presses Ctrl+C at a prompt, so the command stops
 * with the terminal put back as it was (D-719).
 */
final class PromptCancelled extends RuntimeException implements ConsoleException
{
}
