<?php

/**
 * Process runner interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Runs an external process in the foreground and returns its exit code.
 * Commands such as `serve` depend on this rather than calling `proc_open()`
 * themselves, so tests can swap in a recorder.
 */
interface ProcessRunner
{
	/**
	 * Runs the command (an argument list, never a shell string) and waits
	 * for it to finish.
	 *
	 * @param  non-empty-list<string> $command
	 * @throws ProcessFailed When the process can't be started.
	 */
	public function run(array $command, ?string $cwd = null): int;
}
