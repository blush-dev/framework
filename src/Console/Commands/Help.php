<?php

/**
 * Help command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\CommandRegistry;
use Blush\Console\ExitCode;
use Blush\Console\HelpFormatter;
use Blush\Console\Output;

/**
 * Shows help for a command: `help cache:clear` does the same as
 * `cache:clear --help`. Without a command, it lists them all.
 */
#[Command('help', 'Show help for a command.')]
final readonly class Help
{
	public function __construct(
		private CommandRegistry $commands,
		private HelpFormatter $help
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The command to show help for.')] ?string $command = null
	): ExitCode {
		if ($command === null) {
			$this->help->commands($this->commands, $output);

			return ExitCode::Success;
		}

		$signature = $this->commands->signature($command);

		if ($signature === null) {
			$output->error(sprintf('Command "%s" is not defined.', $command));

			return ExitCode::Invalid;
		}

		$this->help->command($signature, $output);

		return ExitCode::Success;
	}
}
