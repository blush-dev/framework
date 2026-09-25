<?php

/**
 * Input value type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

use BackedEnum;
use Blush\Console\InvalidInput;

/**
 * The type of a single argument or option value, read from the parameter's
 * declared type. `cast()` turns raw command-line text into that type.
 */
enum ValueType
{
	case String;
	case Int;
	case Float;
	case Bool;
	case Enum;

	/**
	 * Casts raw text to this type. `$label` names the input in error
	 * messages (such as `the "port" option`), and `$enum` is the backed
	 * enum class for `Enum`.
	 *
	 * @param  ?class-string<BackedEnum> $enum
	 * @throws InvalidInput
	 */
	public function cast(string $raw, string $label, ?string $enum = null): string|int|float|bool|BackedEnum
	{
		return match ($this) {
			self::String => $raw,
			self::Int    => filter_var($raw, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw $this->invalid($label, 'an integer', $raw),
			self::Float  => filter_var($raw, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? throw $this->invalid($label, 'a number', $raw),
			self::Bool   => filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw $this->invalid($label, 'true or false', $raw),
			self::Enum   => $this->castEnum($raw, $label, $enum)
		};
	}

	/**
	 * Returns the enum case whose value matches the raw text.
	 *
	 * @param  ?class-string<BackedEnum> $enum
	 * @throws InvalidInput
	 */
	private function castEnum(string $raw, string $label, ?string $enum): BackedEnum
	{
		if ($enum === null) {
			throw new InvalidInput(sprintf('No enum is declared for %s.', $label));
		}

		foreach ($enum::cases() as $case) {
			if ((string) $case->value === $raw) {
				return $case;
			}
		}

		throw $this->invalid($label, 'one of: ' . implode(', ', self::enumValues($enum)), $raw);
	}

	/**
	 * Returns the values of a backed enum's cases, as text.
	 *
	 * @param  class-string<BackedEnum> $enum
	 * @return list<string>
	 */
	public static function enumValues(string $enum): array
	{
		return array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());
	}

	/**
	 * Builds an invalid-value exception.
	 */
	private function invalid(string $label, string $expected, string $raw): InvalidInput
	{
		return new InvalidInput(sprintf('The %s must be %s; "%s" given.', $label, $expected, $raw));
	}
}
