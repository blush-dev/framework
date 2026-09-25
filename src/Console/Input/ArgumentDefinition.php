<?php

/**
 * Argument definition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

use BackedEnum;

/**
 * Describes one positional argument of a command: the `__invoke()` parameter
 * it fills, its type, and whether it's required or collects the rest.
 */
final readonly class ArgumentDefinition
{
	/**
	 * @param ?class-string<BackedEnum> $enum
	 */
	public function __construct(
		public string $name,
		public string $parameter,
		public ValueType $type,
		public string $description = '',
		public bool $required = true,
		public bool $variadic = false,
		public mixed $default = null,
		public ?string $enum = null
	) {}

	/**
	 * Casts a raw value.
	 */
	public function cast(string $raw): mixed
	{
		return $this->type->cast($raw, sprintf('"%s" argument', $this->name), $this->enum);
	}

	/**
	 * Returns the argument as it appears in a usage line: `<name>`,
	 * `[<name>]`, or `[<name>...]`.
	 */
	public function usage(): string
	{
		$usage = "<{$this->name}>" . ($this->variadic ? '...' : '');

		return $this->required ? $usage : "[{$usage}]";
	}
}
