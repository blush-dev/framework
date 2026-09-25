<?php

/**
 * System process runner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Override;

/**
 * Runs processes with `proc_open()`, sharing this process's standard
 * streams. The argument-list form bypasses the shell entirely.
 */
final readonly class SystemProcessRunner implements ProcessRunner
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function run(array $command, ?string $cwd = null): int
	{
		$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);

		if ($process === false) {
			throw new ProcessFailed(sprintf('Could not start "%s".', $command[0]));
		}

		return proc_close($process);
	}
}
