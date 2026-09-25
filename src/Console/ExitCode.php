<?php

/**
 * Exit code.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * The exit status a command returns.
 */
enum ExitCode: int
{
	case Success = 0;
	case Failure = 1;
	case Invalid = 2;
}
