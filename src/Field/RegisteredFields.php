<?php

/**
 * Registered fields.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Every field registered in code (D-790), core's first. Core's fields are
 * each place's base, which its own fields start with (a type may redefine
 * one), so they're never attached after them; their keys are reserved.
 */
final readonly class RegisteredFields
{
	/**
	 * @param list<RegisteredField> $fields
	 */
	public function __construct(public array $fields = [])
	{}

	/**
	 * Returns the extensions' fields on a place, in the order registered.
	 *
	 * @return list<RegisteredField>
	 */
	public function for(string $target): array
	{
		return array_values(array_filter($this->fields, static fn (RegisteredField $field): bool => ! $field->isCore() && $field->attachesTo($target)));
	}

	/**
	 * Returns core's fields on a place.
	 *
	 * @return list<Field>
	 */
	public function core(string $target): array
	{
		return array_values(array_map(
			static fn (RegisteredField $field): Field => $field->field,
			array_filter($this->fields, static fn (RegisteredField $field): bool => $field->isCore() && $field->attachesTo($target))
		));
	}

	/**
	 * Returns the extensions' fields as data for a compiled cache; core's
	 * are code that `fromArray()` is given again (and may hold fields no
	 * definition can name, such as a reference).
	 *
	 * @return list<array{owner: string, targets: list<string>, field: array<string, mixed>}>
	 */
	public function toArray(): array
	{
		return array_values(array_map(
			static fn (RegisteredField $field): array => $field->toArray(),
			array_filter($this->fields, static fn (RegisteredField $field): bool => ! $field->isCore())
		));
	}

	/**
	 * Rebuilds the fields from `toArray()`'s output, after core's.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  list<RegisteredField>   $core
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory, array $core = []): self
	{
		$fields = $core;

		foreach ($data as $item) {
			if (is_array($item)) {
				$fields[] = RegisteredField::fromArray($item, $factory);
			}
		}

		return new self($fields);
	}
}
