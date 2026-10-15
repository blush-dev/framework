<?php

/**
 * Field layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * The library fields a place carries (D-858, D-859), in order: each a
 * `PlacedField`, or a `FieldSection` of them. A content type's is
 * written as its definition's `fields` (D-863):
 *
 *     "fields": ["difficulty", {"section": "Timing", "fields": [{"field": "cook_time", "required": true}]}]
 *
 * A field is placed once.
 */
final readonly class FieldLayout
{
	/**
	 * @param  list<PlacedField|FieldSection> $items
	 * @throws InvalidSchema When a field is placed twice.
	 */
	public function __construct(public array $items = [])
	{
		$names = array_map(static fn (PlacedField $field): string => $field->name, $this->fields());

		foreach (array_count_values($names) as $name => $count) {
			if ($count > 1) {
				throw new InvalidSchema(sprintf('The "%s" field is listed more than once.', $name));
			}
		}
	}

	/**
	 * Returns every placed field, sections' in their place, in order.
	 *
	 * @return list<PlacedField>
	 */
	public function fields(): array
	{
		$fields = [];

		foreach ($this->items as $item) {
			array_push($fields, ...($item instanceof FieldSection ? $item->fields : [$item]));
		}

		return $fields;
	}

	/**
	 * Returns the names of its fields, in order.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		return array_map(static fn (PlacedField $field): string => $field->name, $this->fields());
	}

	/**
	 * Returns a placed field by name, if it's here.
	 */
	public function find(string $name): ?PlacedField
	{
		return array_find($this->fields(), static fn (PlacedField $field): bool => $field->name === $name);
	}

	/**
	 * Returns whether it places a field.
	 */
	public function has(string $name): bool
	{
		return $this->find($name) !== null;
	}

	/**
	 * Returns the fields outside any section, in order.
	 *
	 * @return list<PlacedField>
	 */
	public function loose(): array
	{
		return array_values(array_filter($this->items, static fn (PlacedField|FieldSection $item): bool => $item instanceof PlacedField));
	}

	/**
	 * Returns its sections, in order.
	 *
	 * @return list<FieldSection>
	 */
	public function sections(): array
	{
		return array_values(array_filter($this->items, static fn (PlacedField|FieldSection $item): bool => $item instanceof FieldSection));
	}

	/**
	 * Returns whether it places nothing.
	 */
	public function isEmpty(): bool
	{
		return $this->items === [];
	}

	/**
	 * Returns it without a field, wherever it's placed; its section stays.
	 */
	public function without(string $name): self
	{
		$keep  = static fn (PlacedField $field): bool => $field->name !== $name;
		$items = [];

		foreach ($this->items as $item) {
			if ($item instanceof FieldSection) {
				$items[] = new FieldSection($item->label, array_values(array_filter($item->fields, $keep)), $item->description);
			} elseif ($keep($item)) {
				$items[] = $item;
			}
		}

		return new self($items);
	}

	/**
	 * Builds one from a definition's `fields`.
	 *
	 * @throws InvalidSchema
	 */
	public static function fromArray(mixed $data, string $subject): self
	{
		if ($data === null) {
			return new self();
		}

		if (! is_array($data) || ! array_is_list($data)) {
			throw new InvalidSchema(sprintf('%s "fields" must be a list of library fields and sections.', $subject));
		}

		$items = [];

		foreach ($data as $item) {
			$items[] = is_array($item) && array_key_exists('section', $item)
				? FieldSection::fromArray($item, $subject)
				: PlacedField::fromArray($item, $subject);
		}

		try {
			return new self($items);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('%s: %s', $subject, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns it as a definition's `fields` writes it.
	 *
	 * @return list<string|array<string, mixed>>
	 */
	public function toArray(): array
	{
		return array_map(static fn (PlacedField|FieldSection $item): string|array => $item->toArray(), $this->items);
	}
}
