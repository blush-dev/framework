<?php

/**
 * Placed field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Stringable;

/**
 * A library field a place carries (D-858), by its name, with what the
 * place changes about how it's asked for: its label, its help, and
 * whether it's required (to publish, D-767). Each left `null` is the
 * field's own. Nothing about the value changes per place.
 */
final readonly class PlacedField
{
	/**
	 * @param string  $name        The library field's name.
	 * @param ?string $label       The place's label for it, or `null` for the field's.
	 * @param ?string $description The place's help for it, or `null` for the field's.
	 * @param ?bool   $required    Whether the place requires it, or `null` for the field's.
	 * @throws InvalidSchema When the name isn't a library field's.
	 */
	public function __construct(
		public string $name,
		public ?string $label = null,
		public ?string $description = null,
		public ?bool $required = null
	) {
		if (preg_match(LibraryField::NAME_PATTERN, $name) !== 1) {
			throw new InvalidSchema(sprintf('"%s" can\'t be a library field\'s name; use lowercase letters, digits, and "_".', $name));
		}
	}

	/**
	 * Returns whether the place changes anything about the field.
	 */
	public function changes(): bool
	{
		return $this->label !== null || $this->description !== null || $this->required !== null;
	}

	/**
	 * Returns the field as the place asks for it.
	 */
	public function apply(Field $field): Field
	{
		$field = $this->label === null ? $field : $field->labeled($this->label);
		$field = $this->description === null ? $field : $field->described($this->description);

		return $this->required === null ? $field : $field->required($this->required);
	}

	/**
	 * Builds one from an item of a type's `fields` (D-863): a key, or an
	 * object with `field` and the place's changes.
	 *
	 * @throws InvalidSchema
	 */
	public static function fromArray(mixed $item, string $subject): self
	{
		if (is_string($item)) {
			return new self($item);
		}

		if (! is_array($item)) {
			throw new InvalidSchema(sprintf('%s: each of its "fields" is a library field\'s key, or an object with "field".', $subject));
		}

		// A field defined here, as types once did: it's the library's now.
		if (! array_key_exists('field', $item) && (array_key_exists('name', $item) || array_key_exists('type', $item))) {
			throw new InvalidSchema(sprintf('%s: its "fields" list library fields by key; a field is defined in the field library (user/data/fields), not on the type.', $subject));
		}

		$unknown = array_diff(array_map(strval(...), array_keys($item)), ['field', 'label', 'description', 'required']);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('%s: one of its "fields" has unknown keys: %s.', $subject, implode(', ', $unknown)));
		}

		$definition = new Definition($item, $subject);
		$required   = $item['required'] ?? null;

		if ($required !== null && ! is_bool($required)) {
			throw new InvalidSchema(sprintf('%s: a field\'s "required" must be true or false.', $subject));
		}

		return new self(
			$definition->string('field'),
			self::text($definition->nullableString('label')),
			self::text($definition->nullableString('description')),
			$required
		);
	}

	/**
	 * Returns it as a type's `fields` writes it: its key alone when the
	 * place changes nothing.
	 *
	 * @return string|array{field: string, label?: string, description?: string, required?: bool}
	 */
	public function toArray(): string|array
	{
		if (! $this->changes()) {
			return $this->name;
		}

		return [
			'field' => $this->name,
			...($this->label === null ? [] : ['label' => $this->label]),
			...($this->description === null ? [] : ['description' => $this->description]),
			...($this->required === null ? [] : ['required' => $this->required])
		];
	}

	/**
	 * Returns text with an empty one as none.
	 */
	private static function text(string|Stringable|null $text): ?string
	{
		$text = $text === null ? null : trim((string) $text);

		return $text === '' ? null : $text;
	}
}
