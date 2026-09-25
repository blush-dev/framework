<?php

/**
 * List command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\CommandRegistry;
use Blush\Console\ExitCode;
use Blush\Console\HelpFormatter;
use Blush\Console\Output;

/**
 * Lists the available commands. This runs when no command is given.
 */
#[Command('list', 'List the available commands.')]
final readonly class ListCommands
{
	public function __construct(
		private CommandRegistry $commands,
		private HelpFormatter $help
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$this->help->commands($this->commands, $output);

		return ExitCode::Success;
	}
}
