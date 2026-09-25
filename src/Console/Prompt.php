<?php

/**
 * Console prompts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Closure;
use InvalidArgumentException;
use NoDiscard;

/**
 * Asks the user questions. When the console isn't interactive (`-n`, or
 * input isn't a terminal), every prompt returns its default without asking,
 * and a prompt with no default throws `InvalidInput`, so scripted runs fail
 * loudly instead of hanging.
 */
final readonly class Prompt
{
	/**
	 * @param resource $input
	 */
	public function __construct(
		private Output $output,
		private mixed $input,
		public bool $interactive = true
	) {
		if (! is_resource($input)) {
			throw new InvalidArgumentException('Prompt input must be a resource.');
		}
	}

	/**
	 * Builds prompts that read from standard input, interactive only when
	 * it's a terminal.
	 */
	public static function forTerminal(Output $output): self
	{
		return new self($output, STDIN, stream_isatty(STDIN));
	}

	/**
	 * Returns a copy that writes questions to other output.
	 */
	#[NoDiscard]
	public function withOutput(Output $output): self
	{
		return clone($this, ['output' => $output]);
	}

	/**
	 * Returns a copy with interactivity turned on or off.
	 */
	#[NoDiscard]
	public function withInteractive(bool $interactive): self
	{
		return clone($this, ['interactive' => $interactive]);
	}

	/**
	 * Asks a yes/no question.
	 *
	 * @throws InvalidInput When no answer can be read.
	 */
	public function confirm(string $question, bool $default = false): bool
	{
		$hint = $default ? 'Y/n' : 'y/N';

		$answer = $this->ask("{$question} [{$hint}]", $default ? 'yes' : 'no', static function (string $answer): ?string {
			return in_array(strtolower($answer), ['y', 'yes', 'n', 'no'], true) ? null : 'Please answer yes or no.';
		});

		return in_array(strtolower($answer), ['y', 'yes'], true);
	}

	/**
	 * Asks for text. An empty answer takes the default. `$validate`
	 * returns an error message for an unacceptable answer (the question is
	 * asked again) or `null` to accept it.
	 *
	 * @param  ?Closure(string): ?string $validate
	 * @throws InvalidInput When no answer can be read.
	 */
	public function ask(string $question, ?string $default = null, ?Closure $validate = null): string
	{
		if (! $this->interactive) {
			return $default ?? throw new InvalidInput(sprintf('"%s" needs an answer, but the console is not interactive.', $question));
		}

		while (true) {
			$suffix = $default === null || str_contains($question, '[') ? '' : " [{$default}]";

			$this->output->write($this->output->style($question, Style::Cyan) . "{$suffix} ", Verbosity::Quiet);

			$answer = $this->readLine() ?? throw new InvalidInput(sprintf('No answer was given for "%s".', $question));
			$answer = $answer === '' ? ($default ?? '') : $answer;

			$error = $answer === '' ? 'An answer is required.' : ($validate === null ? null : $validate($answer));

			if ($error === null) {
				return $answer;
			}

			$this->output->error($error);
		}
	}

	/**
	 * Asks the user to pick one of several choices, by number or by value.
	 *
	 * @param  list<string> $choices
	 * @throws InvalidInput When no answer can be read.
	 */
	public function choice(string $question, array $choices, ?string $default = null): string
	{
		if ($this->interactive) {
			foreach ($choices as $index => $choice) {
				$this->output->line(sprintf('  [%d] %s', $index + 1, $choice), Verbosity::Quiet);
			}
		}

		$answer = $this->ask($question, $default, static function (string $answer) use ($choices): ?string {
			return in_array($answer, $choices, true) || isset($choices[(int) $answer - 1])
				? null
				: 'Please pick one of the listed choices.';
		});

		return in_array($answer, $choices, true) ? $answer : $choices[(int) $answer - 1];
	}

	/**
	 * Asks for a secret without echoing it, when input is a terminal.
	 *
	 * @throws InvalidInput When no answer can be read.
	 */
	public function secret(string $question): string
	{
		if (! $this->interactive) {
			throw new InvalidInput(sprintf('"%s" needs an answer, but the console is not interactive.', $question));
		}

		$hide = stream_isatty($this->input) && PHP_OS_FAMILY !== 'Windows';

		$this->output->write($this->output->style($question, Style::Cyan) . ' ', Verbosity::Quiet);

		if ($hide) {
			shell_exec('stty -echo');
		}

		try {
			$answer = $this->readLine();
		} finally {
			if ($hide) {
				shell_exec('stty echo');
				$this->output->newLine(1, Verbosity::Quiet);
			}
		}

		return $answer ?? throw new InvalidInput(sprintf('No answer was given for "%s".', $question));
	}

	/**
	 * Reads one line of input without its line ending, or `null` at the
	 * end of input.
	 */
	private function readLine(): ?string
	{
		$line = fgets($this->input);

		return $line === false ? null : rtrim($line, "\r\n");
	}
}
