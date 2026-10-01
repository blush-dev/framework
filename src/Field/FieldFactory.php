<?php

/**
 * Field factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Builds fields and schemas from definition arrays, looking up each
 * field's class by its `type` in the registry:
 *
 *     $factory->fromArray(['name' => 'rating', 'type' => 'number', 'max' => 5]);
 *
 * A `class` key naming a `Field` subclass wins over the registry. Compiled
 * caches use it for extension field types (see `Field::toArray()`).
 *
 * Fields are value objects, so the classes' own `fromArray()` builds them
 * rather than the container.
 */
final readonly class FieldFactory
{
	public function __construct(private FieldRegistry $registry)
	{}

	/**
	 * Builds one field.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public function fromArray(array $data): Field
	{
		$type  = $data['type'] ?? FieldType::Text->value;
		$class = $data['class'] ?? null;
		$class = is_string($class) && is_subclass_of($class, Field::class)
			? $class
			: (is_string($type) ? $this->registry->get($type) : null);

		if ($class === null) {
			throw new InvalidSchema(sprintf(
				'Field "%s" has an unknown type "%s".',
				is_string($data['name'] ?? null) ? $data['name'] : '',
				is_scalar($type) ? (string) $type : get_debug_type($type)
			));
		}

		return $class::fromArray($data, $this);
	}

	/**
	 * Builds a schema from field definitions, in either shape
	 * (`definitions()`).
	 *
	 * @param  array<array-key, mixed> $fields
	 * @throws InvalidSchema
	 */
	public function schema(array $fields, bool $closed = false): Schema
	{
		return new Schema(array_map($this->fromArray(...), self::definitions($fields)), $closed);
	}

	/**
	 * Returns field definitions as a list (D-337). They may be written as a
	 * list of definitions with names, as types and media fields have them,
	 * or as a map of names to definitions, as theme settings and menu
	 * fields have them; in a map, the key is the name.
	 *
	 * @param  array<array-key, mixed> $fields
	 * @return list<array<array-key, mixed>>
	 * @throws InvalidSchema
	 */
	public static function definitions(array $fields): array
	{
		$list = [];

		foreach ($fields as $key => $definition) {
			if (! is_array($definition)) {
				throw new InvalidSchema(sprintf('Fields must be field definitions; found %s.', get_debug_type($definition)));
			}

			$list[] = is_string($key) ? [...$definition, 'name' => $key] : $definition;
		}

		return $list;
	}
}
