<?php

/**
 * Argv parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

use Blush\Console\InvalidInput;

/**
 * Splits command-line tokens into positional arguments and options against
 * an `InputDefinition`. It understands:
 *
 * - Long options: `--flag`, `--name value`, `--name=value`, and `--no-flag`
 *   to negate a flag.
 * - Short options: `-f`, bundled flags (`-vq`), and values attached or
 *   separate (`-p8000`, `-p=8000`, `-p 8000`).
 * - `--`, after which every token is an argument.
 *
 * Values aren't cast here; `InputBinder` does that.
 */
final readonly class ArgvParser
{
	/**
	 * Parses the tokens. In lenient mode, unknown options are skipped
	 * instead of rejected, which lets the console read global options
	 * before it knows the command.
	 *
	 * @param  list<string> $tokens
	 * @throws InvalidInput
	 */
	public function parse(array $tokens, InputDefinition $definition, bool $lenient = false): ParsedInput
	{
		$arguments = [];
		$options   = [];
		$count     = count($tokens);
		$literal   = false;

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			if ($literal || $token === '-' || ! str_starts_with($token, '-')) {
				$arguments[] = $token;
				continue;
			}

			if ($token === '--') {
				$literal = true;
				continue;
			}

			if (str_starts_with($token, '--')) {
				$i = $this->parseLong($tokens, $i, $definition, $lenient, $options);
				continue;
			}

			$i = $this->parseShort($tokens, $i, $definition, $lenient, $options);
		}

		return new ParsedInput($arguments, $options);
	}

	/**
	 * Parses a long option at `$i`, returning the index of the last token
	 * it consumed.
	 *
	 * @param  list<string>                     $tokens
	 * @param  array<string, list<string|bool>> $options
	 * @throws InvalidInput
	 */
	private function parseLong(array $tokens, int $i, InputDefinition $definition, bool $lenient, array &$options): int
	{
		$name  = substr($tokens[$i], 2);
		$value = null;

		if (str_contains($name, '=')) {
			[$name, $value] = explode('=', $name, 2);
		}

		$option = $definition->option($name);

		// `--no-name` negates the `--name` flag.
		if ($option === null && str_starts_with($name, 'no-') && $value === null) {
			$negated = $definition->option(substr($name, 3));

			if ($negated !== null && ! $negated->acceptsValue()) {
				$options[$negated->name][] = false;

				return $i;
			}
		}

		if ($option === null) {
			return $lenient ? $i : throw new InvalidInput(sprintf('The "--%s" option does not exist.', $name));
		}

		if (! $option->acceptsValue()) {
			if ($value !== null) {
				throw new InvalidInput(sprintf('The "--%s" option does not accept a value.', $name));
			}

			$options[$option->name][] = true;

			return $i;
		}

		if ($value === null) {
			$value = $tokens[$i + 1] ?? throw new InvalidInput(sprintf('The "--%s" option requires a value.', $name));
			$i++;
		}

		$options[$option->name][] = $value;

		return $i;
	}

	/**
	 * Parses a short option or a bundle of them at `$i`, returning the
	 * index of the last token it consumed.
	 *
	 * @param  list<string>                     $tokens
	 * @param  array<string, list<string|bool>> $options
	 * @throws InvalidInput
	 */
	private function parseShort(array $tokens, int $i, InputDefinition $definition, bool $lenient, array &$options): int
	{
		$chars  = substr($tokens[$i], 1);
		$length = strlen($chars);

		for ($c = 0; $c < $length; $c++) {
			$option = $definition->shortOption($chars[$c]);

			if ($option === null) {
				if ($lenient) {
					continue;
				}

				throw new InvalidInput(sprintf('The "-%s" option does not exist.', $chars[$c]));
			}

			if (! $option->acceptsValue()) {
				$options[$option->name][] = true;
				continue;
			}

			// The rest of the token is the value (`-p8000` or
			// `-p=8000`); otherwise the next token is.
			$value = ltrim(substr($chars, $c + 1), '=');

			if ($value === '') {
				$value = $tokens[$i + 1] ?? throw new InvalidInput(sprintf('The "-%s" option requires a value.', $chars[$c]));
				$i++;
			}

			$options[$option->name][] = $value;

			break;
		}

		return $i;
	}
}
