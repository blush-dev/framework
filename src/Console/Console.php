<?php

/**
 * Console.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Throwable;
use Blush\Console\Input\ArgvParser;
use Blush\Console\Input\InputBinder;
use Blush\Console\Input\Signature;
use Blush\Container\Container;
use Blush\Core\Framework;
use Blush\Error\ErrorHandler;
use Blush\Error\ExceptionRenderer;

/**
 * Runs a command line against the registered commands. It runs on the same
 * booted application as the web, so commands get the container, config,
 * and everything else.
 *
 * A run reads the global options first (they're all flags, so the command
 * doesn't need to be known yet), applies them to the output and prompts,
 * then finds the command, parses its input against its signature, and
 * calls it through the container. Input errors print the command's usage
 * and exit with `ExitCode::Invalid`; any other exception is logged and
 * rendered to the error stream and exits with `ExitCode::Failure`.
 */
final readonly class Console
{
	public function __construct(
		private Container $container,
		private CommandRegistry $commands,
		private ErrorHandler $errors,
		private ExceptionRenderer $renderer,
		private ArgvParser $parser,
		private InputBinder $binder,
		private HelpFormatter $help
	) {}

	/**
	 * Runs a command line, without the script name. Output defaults to
	 * the process's standard streams and prompts to standard input.
	 *
	 * @param list<string> $argv
	 */
	public function run(array $argv, ?Output $output = null, ?Prompt $prompt = null): ExitCode
	{
		$output ??= Output::forTerminal(getenv());

		$index  = $this->commandIndex($argv);
		$name   = $index === null ? null : $argv[$index];
		$tokens = $argv;

		if ($index !== null) {
			unset($tokens[$index]);
			$tokens = array_values($tokens);
		}

		$globals = GlobalOptions::fromParsed($this->parser->parse($tokens, GlobalOptions::definition(), lenient: true));

		$output = $output->withVerbosity($globals->verbosity);
		$output = $globals->ansi === null ? $output : $output->withAnsi($globals->ansi);

		$prompt ??= Prompt::forTerminal($output);
		$prompt   = $prompt->withOutput($output);
		$prompt   = $globals->interactive ? $prompt : $prompt->withInteractive(false);

		$this->container->instance(Output::class, $output);
		$this->container->instance(Prompt::class, $prompt);

		if ($globals->version) {
			$output->line(sprintf('%s %s', Framework::NAME, Framework::VERSION), Verbosity::Quiet);

			return ExitCode::Success;
		}

		$name    ??= BuiltInCommand::List->value;
		$signature = $this->commands->signature($name);

		if ($signature === null) {
			return $this->unknown($name, $output);
		}

		if ($globals->help) {
			$this->help->command($signature, $output);

			return ExitCode::Success;
		}

		return $this->execute($signature, $tokens, $output);
	}

	/**
	 * Parses the input for a command and runs it.
	 *
	 * @param list<string> $tokens
	 */
	private function execute(Signature $signature, array $tokens, Output $output): ExitCode
	{
		try {
			$definition = $signature->definition->withOptionsOf(GlobalOptions::definition());
			$values     = $this->binder->bind($definition, $this->parser->parse($tokens, $definition));

			// Only the command's own parameters are passed; the global
			// options have already been applied.
			$parameters = [];

			foreach ([...$signature->definition->arguments, ...$signature->definition->options()] as $input) {
				$parameters[$input->parameter] = $values[$input->parameter];
			}

			$result = $this->container->call([$this->container->make($signature->class), '__invoke'], $parameters);

			return $result instanceof ExitCode ? $result : ExitCode::Failure;
		} catch (InvalidInput $e) {
			$output->error($e->getMessage());
			$output->writeError(PHP_EOL . 'Usage: ' . Framework::BINARY . ' ' . $signature->usage() . PHP_EOL);

			return ExitCode::Invalid;
		} catch (Throwable $e) {
			$this->errors->report($e);

			try {
				$output->writeError($this->renderer->render($e));
			} catch (Throwable) {
				$output->error($e->getMessage());
			}

			return ExitCode::Failure;
		}
	}

	/**
	 * Reports an unknown command, suggesting close names.
	 */
	private function unknown(string $name, Output $output): ExitCode
	{
		$output->error(sprintf('Command "%s" is not defined.', $name));

		$suggestions = array_values(array_filter(
			$this->commands->names(),
			static fn (string $candidate): bool => levenshtein($name, $candidate) <= max(2, intdiv(strlen($name), 3))
				|| str_starts_with($candidate, $name)
		));

		sort($suggestions);

		if ($suggestions !== []) {
			$output->writeError(PHP_EOL . 'Did you mean one of these?' . PHP_EOL);

			foreach ($suggestions as $suggestion) {
				$output->writeError("    {$suggestion}" . PHP_EOL);
			}
		}

		return ExitCode::Invalid;
	}

	/**
	 * Returns the index of the command name: the first token that isn't an
	 * option, before any `--`. Global options are all flags, so none of
	 * them can take the next token as a value.
	 *
	 * @param list<string> $argv
	 */
	private function commandIndex(array $argv): ?int
	{
		foreach ($argv as $index => $token) {
			if ($token === '--') {
				return null;
			}

			if ($token === '-' || ! str_starts_with($token, '-')) {
				return $index;
			}
		}

		return null;
	}
}
