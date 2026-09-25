<?php

/**
 * Parsed input.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

/**
 * A command line split into raw positional arguments and options, before
 * any casting. Each option maps to every value it was given, in order:
 * `true` or `false` for a flag (false when negated with `--no-`), text
 * otherwise.
 */
final readonly class ParsedInput
{
	/**
	 * @param list<string>                         $arguments
	 * @param array<string, list<string|bool>>     $options
	 */
	public function __construct(
		public array $arguments = [],
		public array $options = []
	) {}

	/**
	 * Whether the option was given at all.
	 */
	public function has(string $option): bool
	{
		return isset($this->options[$option]);
	}

	/**
	 * Returns how many times a flag was given (`-vvv` counts 3).
	 */
	public function count(string $option): int
	{
		return count(array_filter($this->options[$option] ?? [], static fn (string|bool $value): bool => $value !== false));
	}

	/**
	 * Returns an option's last value, or `null` when it wasn't given.
	 */
	public function last(string $option): string|bool|null
	{
		return array_last($this->options[$option] ?? []);
	}
}
