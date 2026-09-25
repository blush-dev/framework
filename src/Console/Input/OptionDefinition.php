<?php

/**
 * Option definition.
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
 * Describes one option of a command (or a global option): its long and
 * short names, the parameter it fills, and how its value is read. A `Bool`
 * option is a flag; a list option may be repeated.
 */
final readonly class OptionDefinition
{
	/**
	 * @param ?class-string<BackedEnum> $enum
	 */
	public function __construct(
		public string $name,
		public string $parameter,
		public ValueType $type,
		public string $description = '',
		public ?string $short = null,
		public bool $list = false,
		public mixed $default = null,
		public ?string $enum = null
	) {}

	/**
	 * Whether the option takes a value (anything but a flag).
	 */
	public function acceptsValue(): bool
	{
		return $this->type !== ValueType::Bool;
	}

	/**
	 * Casts a raw value.
	 */
	public function cast(string $raw): mixed
	{
		return $this->type->cast($raw, sprintf('"--%s" option', $this->name), $this->enum);
	}

	/**
	 * Returns the option as it appears in help: `-p, --port=PORT`.
	 */
	public function usage(): string
	{
		$usage = ($this->short === null ? '    ' : "-{$this->short}, ") . "--{$this->name}";

		if ($this->acceptsValue()) {
			$usage .= '=' . strtoupper($this->name) . ($this->list ? '...' : '');
		}

		return $usage;
	}
}
