<?php

/**
 * Serve MCP command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Auth\Accounts;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Mcp\McpStdio;
use Blush\Tool\ToolContexts;

/**
 * Serves the MCP server over stdio for a local agent (D-751, D-754), as
 * an account, with its capabilities: Claude Code, Claude Desktop, or any
 * client that starts a command. It works whether or not the MCP server
 * is on for the web, since whoever runs it has the site already, and
 * needs no token. A suspended account is refused.
 *
 *     blush mcp --account=jane
 */
#[Command('mcp', 'Serve the MCP server over stdio, as an account, for a local agent.')]
final readonly class ServeMcp
{
	public function __construct(
		private Accounts $accounts,
		private ToolContexts $contexts,
		private McpStdio $stdio
	) {}

	public function __invoke(
		Output $output,
		#[Option('The account the agent acts as, by username.')] ?string $account = null
	): ExitCode {
		$found = $account === null ? null : $this->accounts->find($account);

		if ($found === null) {
			$output->writeError($account === null
				? "Name the account the agent acts as: blush mcp --account={username}\n"
				: sprintf("There's no \"%s\" account.\n", $account));

			return ExitCode::Failure;
		}

		if ($found->suspended) {
			$output->writeError(sprintf("%s is suspended, so it can't act through MCP.\n", $found->username));

			return ExitCode::Failure;
		}

		$this->stdio->serve(STDIN, $output, $this->contexts->forAccount($found));

		return ExitCode::Success;
	}
}
