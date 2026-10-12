<?php

/**
 * MCP over stdio.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use JsonException;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Support\RequestMemo;
use Blush\Tool\ToolContext;

/**
 * Answers MCP over stdio (D-751): one JSON-RPC message a line on
 * standard input, each answer a line on standard output, until the input
 * ends. Nothing else goes to standard output; problems go to standard
 * error. The same server as over the web, for local agents.
 */
final readonly class McpStdio
{
	public function __construct(
		private McpServer $server,
		private ?RequestMemo $memo = null
	) {}

	/**
	 * Serves messages from a stream until it ends.
	 *
	 * @param resource $input
	 */
	public function serve($input, Output $output, ToolContext $context): void
	{
		while (($line = fgets($input)) !== false) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			// Each message starts from what's stored now, as each web
			// request does (D-805).
			$this->memo?->forget();

			try {
				$answer = $this->server->handle(json_decode($line, true, 64, JSON_THROW_ON_ERROR), $context);
			} catch (JsonException) {
				$answer = McpServer::parseError();
			}

			if ($answer !== null) {
				try {
					$output->line(json_encode($answer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), Verbosity::Quiet);
				} catch (JsonException $error) {
					$output->writeError("The answer couldn't be written: {$error->getMessage()}\n");
				}
			}
		}
	}
}
