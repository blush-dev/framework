<?php

/**
 * Field section.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A heading a place arranges some of its fields under (D-858): only
 * layout, saving nothing to entries. A field doesn't know its section.
 */
final readonly class FieldSection
{
	/**
	 * @param string            $label       The heading.
	 * @param list<PlacedField> $fields      Its fields, in order.
	 * @param string            $description A line under the heading.
	 * @throws InvalidSchema When it has no heading.
	 */
	public function __construct(
		public string $label,
		public array $fields = [],
		public string $description = ''
	) {
		if (trim($label) === '') {
			throw new InvalidSchema('A section of a type\'s fields needs a heading, its "section".');
		}
	}

	/**
	 * Builds one from an item of a type's `fields`, `{"section": "Timing", "fields":
	 * [ … ]}`.
	 *
	 * @param  array<array-key, mixed> $item
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $item, string $subject): self
	{
		$unknown = array_diff(array_map(strval(...), array_keys($item)), ['section', 'description', 'fields']);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('%s: a section has unknown keys: %s.', $subject, implode(', ', $unknown)));
		}

		$definition = new Definition($item, $subject);
		$fields     = $item['fields'] ?? [];

		if (! is_array($fields) || ! array_is_list($fields)) {
			throw new InvalidSchema(sprintf('%s: a section\'s "fields" must be a list.', $subject));
		}

		$placed = [];

		foreach ($fields as $field) {
			if (is_array($field) && array_key_exists('section', $field)) {
				throw new InvalidSchema(sprintf('%s: a section can\'t hold a section; sections are one level.', $subject));
			}

			$placed[] = PlacedField::fromArray($field, $subject);
		}

		return new self(trim($definition->string('section')), $placed, trim($definition->string('description')));
	}

	/**
	 * Returns it as a type's `fields` writes it.
	 *
	 * @return array{section: string, description?: string, fields: list<string|array<string, mixed>>}
	 */
	public function toArray(): array
	{
		return [
			'section' => $this->label,
			...($this->description === '' ? [] : ['description' => $this->description]),
			'fields'  => array_map(static fn (PlacedField $field): string|array => $field->toArray(), $this->fields)
		];
	}
}
