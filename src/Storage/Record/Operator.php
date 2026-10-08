<?php

/**
 * Operator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * How a condition compares a record's value (D-643). Every driver
 * answers every one alike, as the conformance suite pins:
 *
 * - A missing key's value is `null`.
 * - `=`, `!=`, `in`, and `not in` compare strictly: numbers equal
 *   numbers (`1` and `1.0`), never numeric strings; text is
 *   case-sensitive; `null` equals only `null`. `!=` and `not in` match
 *   `null`.
 * - `<`, `<=`, `>`, `>=`, and `between` (inclusive) compare numbers
 *   with numbers and text with text, byte by byte (ISO 8601 dates sort
 *   as text); anything else, `null` included, doesn't match.
 * - `like` matches text against a pattern, `%` for any run and `_` for
 *   one character (`\` escapes either), without regard to case.
 * - `contains` matches a list holding the value.
 * - `null` and `not null` take no value.
 */
enum Operator: string
{
	case Equal          = '=';
	case NotEqual       = '!=';
	case Less           = '<';
	case LessOrEqual    = '<=';
	case Greater        = '>';
	case GreaterOrEqual = '>=';
	case In             = 'in';
	case NotIn          = 'not in';
	case Between        = 'between';
	case Like           = 'like';
	case Contains       = 'contains';
	case Null           = 'null';
	case NotNull        = 'not null';

	/**
	 * Checks a value the operator is given.
	 *
	 * @throws InvalidRecordQuery When the operator can't take it.
	 */
	public function check(mixed $value): void
	{
		$scalar = static fn (mixed $item): bool => $item === null || is_bool($item) || is_int($item) || is_string($item) || (is_float($item) && is_finite($item));
		$ordered = static fn (mixed $item): bool => is_int($item) || is_string($item) || (is_float($item) && is_finite($item));

		$valid = match ($this) {
			self::Equal, self::NotEqual, self::Contains => $scalar($value),
			self::Less, self::LessOrEqual, self::Greater, self::GreaterOrEqual => $ordered($value),
			self::In, self::NotIn => is_array($value) && array_is_list($value) && array_all($value, static fn (mixed $item): bool => $scalar($item)),
			self::Between => is_array($value) && array_is_list($value) && count($value) === 2 && $ordered($value[0]) && $ordered($value[1]),
			self::Like => is_string($value),
			self::Null, self::NotNull => $value === null
		};

		if (! $valid) {
			throw new InvalidRecordQuery(match ($this) {
				self::In, self::NotIn => sprintf('"%s" takes a list of plain values.', $this->value),
				self::Between => '"between" takes a list of two numbers or two strings.',
				self::Like => '"like" takes a pattern string.',
				self::Null, self::NotNull => sprintf('"%s" takes no value.', $this->value),
				self::Less, self::LessOrEqual, self::Greater, self::GreaterOrEqual => sprintf('"%s" takes a number or a string.', $this->value),
				default => sprintf('"%s" takes a plain value: text, a number, true or false, or null.', $this->value)
			});
		}
	}
}
