<?php

/**
 * Global console options.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Blush\Console\Input\InputDefinition;
use Blush\Console\Input\OptionDefinition;
use Blush\Console\Input\ParsedInput;
use Blush\Console\Input\ValueType;

/**
 * The options every command accepts: help, version, verbosity, ANSI, and
 * interactivity. They're all flags, so they can be read before the command
 * is known.
 */
final readonly class GlobalOptions
{
	public function __construct(
		public bool $help = false,
		public bool $version = false,
		public Verbosity $verbosity = Verbosity::Normal,
		public ?bool $ansi = null,
		public bool $interactive = true
	) {}

	/**
	 * Returns the definition of the global options.
	 */
	public static function definition(): InputDefinition
	{
		$flag = static fn (string $name, ?string $short, string $description): OptionDefinition => new OptionDefinition(
			name: $name,
			parameter: $name,
			type: ValueType::Bool,
			description: $description,
			short: $short,
			default: false
		);

		return new InputDefinition([], [
			$flag('help', 'h', 'Show help for the command.'),
			$flag('quiet', 'q', 'Only show errors.'),
			$flag('verbose', 'v', 'Show more output (-vv and -vvv for even more).'),
			$flag('ansi', null, 'Force colored output.'),
			$flag('no-ansi', null, 'Disable colored output.'),
			$flag('no-interaction', 'n', 'Never ask questions; use defaults.'),
			$flag('version', 'V', 'Show the framework version.')
		]);
	}

	/**
	 * Reads the global options from parsed input.
	 */
	public static function fromParsed(ParsedInput $input): self
	{
		$verbosity = match (true) {
			$input->last('quiet') === true => Verbosity::Quiet,
			default                        => Verbosity::tryFrom(Verbosity::Normal->value + min(3, $input->count('verbose'))) ?? Verbosity::Debug
		};

		$ansi = match (true) {
			$input->last('no-ansi') === true => false,
			$input->last('ansi') === true    => true,
			default                          => null
		};

		return new self(
			help: $input->last('help') === true,
			version: $input->last('version') === true,
			verbosity: $verbosity,
			ansi: $ansi,
			interactive: $input->last('no-interaction') !== true
		);
	}
}
