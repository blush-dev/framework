<?php

/**
 * Command result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Testing;

use Blush\Console\ExitCode;

/**
 * What a command run by `CommandTester` produced.
 */
final readonly class CommandResult
{
	public function __construct(
		public ExitCode $exitCode,
		public string $output,
		public string $errors
	) {
	}

	/**
	 * Whether the command succeeded.
	 */
	public function isSuccessful(): bool
	{
		return $this->exitCode === ExitCode::Success;
	}
}
