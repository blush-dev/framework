<?php

/**
 * Input binder.
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
 * Turns parsed input into the typed, named values a command's `__invoke()`
 * receives. Every argument and option parameter gets a value (its default
 * when not given), so the container never tries to autowire one.
 */
final readonly class InputBinder
{
	/**
	 * Returns `__invoke()` values keyed by parameter name.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidInput
	 */
	public function bind(InputDefinition $definition, ParsedInput $input): array
	{
		return [
			...$this->arguments($definition, $input->arguments),
			...$this->options($definition, $input)
		];
	}

	/**
	 * Binds positional arguments.
	 *
	 * @param  list<string> $raw
	 * @return array<string, mixed>
	 * @throws InvalidInput
	 */
	private function arguments(InputDefinition $definition, array $raw): array
	{
		$values = [];

		foreach ($definition->arguments as $argument) {
			if ($argument->variadic) {
				$values[$argument->parameter] = array_map($argument->cast(...), $raw);
				$raw = [];
				continue;
			}

			if ($raw === []) {
				if ($argument->required) {
					throw new InvalidInput(sprintf('Missing required argument "%s".', $argument->name));
				}

				$values[$argument->parameter] = $argument->default;
				continue;
			}

			$values[$argument->parameter] = $argument->cast(array_shift($raw));
		}

		if ($raw !== []) {
			throw new InvalidInput(sprintf('Too many arguments; unexpected "%s".', $raw[0]));
		}

		return $values;
	}

	/**
	 * Binds options. A flag takes its last value; a list option takes all
	 * of them; any other option takes its last value.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidInput
	 */
	private function options(InputDefinition $definition, ParsedInput $input): array
	{
		$values = [];

		foreach ($definition->options() as $option) {
			$given = $input->options[$option->name] ?? [];

			if ($given === []) {
				$values[$option->parameter] = $option->default;
				continue;
			}

			if (! $option->acceptsValue()) {
				$values[$option->parameter] = array_last($given) === true;
				continue;
			}

			$strings = array_map(strval(...), $given);

			$values[$option->parameter] = $option->list
				? array_map($option->cast(...), $strings)
				: $option->cast(array_last($strings));
		}

		return $values;
	}
}
