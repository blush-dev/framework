<?php

/**
 * Library field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A field the site made, in its field library (D-858): one field, with
 * the controls a form draws it with (D-795), by path from its own name.
 * Its name is its key wherever it's placed, and its value, rules, and
 * settings are the same on every place. A place may change only how
 * it's asked for (`PlacedField`).
 */
final readonly class LibraryField
{
	/**
	 * What a library field's name may be: the admin's rule for a key.
	 */
	public const string NAME_PATTERN = '/^[a-z_][a-z0-9_]*$/';

	/**
	 * Names the admin's addresses use, which no field may take
	 * (`/fields/new`).
	 *
	 * @var list<string>
	 */
	public const array RESERVED = ['new'];

	/**
	 * @throws InvalidSchema When its name isn't a library field's.
	 */
	public function __construct(
		public Field $field,
		public FieldControls $controls = new FieldControls()
	) {
		if (preg_match(self::NAME_PATTERN, $field->name) !== 1) {
			throw new InvalidSchema(sprintf('"%s" can\'t be a library field\'s name; use lowercase letters, digits, and "_".', $field->name));
		}

		if (in_array($field->name, self::RESERVED, true)) {
			throw new InvalidSchema(sprintf('"%s" can\'t be a library field\'s name; the admin uses it.', $field->name));
		}
	}

	/**
	 * Returns its name, its key.
	 */
	public function name(): string
	{
		return $this->field->name;
	}

	/**
	 * Builds one from its record, as `user/data/fields/{name}.json`
	 * writes it, its control an object (`"control": {"name": "radios"}`).
	 * A JSON file's `$schema` (`field.schema.json`) is ignored.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory): self
	{
		unset($data['$schema']);

		$name = is_string($data['name'] ?? null) ? $data['name'] : '';

		try {
			[$fields, $controls] = $factory->withControls([$data]);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('Field "%s": %s', $name, $e->getMessage()), previous: $e);
		}

		return new self($fields[0], $controls);
	}

	/**
	 * Returns it as `fromArray()` takes it, with its name.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->controls->definitions([$this->field])[0];
	}
}
