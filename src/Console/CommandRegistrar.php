<?php

/**
 * Command registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Seeds the registry with the built-in commands, leaving any name an
 * extension or site already registered alone.
 */
final readonly class CommandRegistrar
{
	public function __construct(private CommandRegistry $registry)
	{}

	/**
	 * Registers each built-in command whose name is still free.
	 *
	 * @throws InvalidCommand
	 */
	public function register(): void
	{
		foreach (BuiltInCommand::cases() as $command) {
			$this->registry->registerIf($command->className());
		}
	}
}
