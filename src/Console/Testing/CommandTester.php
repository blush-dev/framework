<?php

/**
 * Command tester.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Testing;

use Blush\Console\Console;
use Blush\Console\Output;
use Blush\Console\ProcessFailed;
use Blush\Console\Prompt;

/**
 * Runs command lines through a `Console` with in-memory streams, for tests.
 * ANSI styling is off. Prompts are non-interactive (they take their
 * defaults) unless answers are given, in which case they're read in order.
 *
 *     $result = new CommandTester($console)->run('cache:clear --config');
 *     $this->assertTrue($result->isSuccessful());
 */
final readonly class CommandTester
{
	public function __construct(private Console $console)
	{}

	/**
	 * Runs a command line, given as a string (split on whitespace) or as
	 * a list of tokens.
	 *
	 * @param string|list<string> $command
	 * @param list<string>        $answers Lines of input for prompts.
	 */
	public function run(string|array $command, array $answers = []): CommandResult
	{
		$argv = is_string($command)
			? preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY) ?: []
			: $command;

		$stdout = $this->memory();
		$stderr = $this->memory();
		$stdin  = $this->memory();

		fwrite($stdin, implode(PHP_EOL, $answers) . ($answers === [] ? '' : PHP_EOL));
		rewind($stdin);

		$output = new Output($stdout, $stderr, ansi: false);
		$code   = $this->console->run($argv, $output, new Prompt($output, $stdin, interactive: $answers !== []));

		return new CommandResult($code, $this->contents($stdout), $this->contents($stderr));
	}

	/**
	 * Opens an in-memory stream.
	 *
	 * @return resource
	 */
	private function memory(): mixed
	{
		return fopen('php://memory', 'w+') ?: throw new ProcessFailed('Could not open a memory stream.');
	}

	/**
	 * Reads a stream from the start.
	 *
	 * @param resource $stream
	 */
	private function contents(mixed $stream): string
	{
		rewind($stream);

		return (string) stream_get_contents($stream);
	}
}
