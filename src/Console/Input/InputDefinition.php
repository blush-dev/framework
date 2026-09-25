<?php

/**
 * Input definition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

use Blush\Console\InvalidCommand;

/**
 * The arguments and options a command line may hold, indexed for the parser.
 * Option names and short names must be unique.
 */
final readonly class InputDefinition
{
	/**
	 * Options keyed by long name.
	 *
	 * @var array<string, OptionDefinition>
	 */
	private array $options;

	/**
	 * Long option names keyed by short name.
	 *
	 * @var array<string, string>
	 */
	private array $shorts;

	/**
	 * @param  list<ArgumentDefinition> $arguments
	 * @param  list<OptionDefinition>   $options
	 * @throws InvalidCommand When two options share a name or short name.
	 */
	public function __construct(
		public array $arguments = [],
		array $options = []
	) {
		$byName = [];
		$shorts = [];

		foreach ($options as $option) {
			if (isset($byName[$option->name])) {
				throw new InvalidCommand(sprintf('The "--%s" option is defined more than once.', $option->name));
			}

			if ($option->short !== null) {
				if (isset($shorts[$option->short])) {
					throw new InvalidCommand(sprintf('The "-%s" short option is defined more than once.', $option->short));
				}

				$shorts[$option->short] = $option->name;
			}

			$byName[$option->name] = $option;
		}

		$this->options = $byName;
		$this->shorts  = $shorts;
	}

	/**
	 * Returns a definition holding this one's arguments and options plus
	 * another's options.
	 *
	 * @throws InvalidCommand
	 */
	public function withOptionsOf(self $other): self
	{
		return new self($this->arguments, [...array_values($this->options), ...$other->options()]);
	}

	/**
	 * Returns every option.
	 *
	 * @return list<OptionDefinition>
	 */
	public function options(): array
	{
		return array_values($this->options);
	}

	/**
	 * Returns an option by long name.
	 */
	public function option(string $name): ?OptionDefinition
	{
		return $this->options[$name] ?? null;
	}

	/**
	 * Returns an option by short name.
	 */
	public function shortOption(string $short): ?OptionDefinition
	{
		return isset($this->shorts[$short]) ? $this->options[$this->shorts[$short]] : null;
	}
}
