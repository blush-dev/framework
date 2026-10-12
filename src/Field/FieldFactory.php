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

use Blush\Field\Fields\ListField;
use Blush\Field\Fields\ObjectField;

/**
 * Builds fields and schemas from definition arrays, looking up each
 * field's class by its `type` in the registry:
 *
 *     $factory->fromArray(['name' => 'rating', 'type' => 'number', 'max' => 5]);
 *
 * A field holds no control (D-795). Definitions that choose controls,
 * as a field set's file writes them, are built with `withControls()`,
 * which parts each into the field and its control (a control names its
 * type when the field names none, D-769) and checks the control can draw
 * its field.
 *
 * A `class` key naming a `Field` subclass wins over the registry. Compiled
 * caches use it for extension field types (see `Field::toArray()`).
 *
 * Fields are value objects, so the classes' own `fromArray()` builds them
 * rather than the container.
 */
final readonly class FieldFactory
{
	public function __construct(
		private FieldRegistry $registry,
		private ControlFactory $controls
	) {}

	/**
	 * Builds one field.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public function fromArray(array $data): Field
	{
		if (array_key_exists('control', $data)) {
			throw new InvalidSchema(sprintf('Field "%s" names a control, but a field holds none; a form chooses it (D-795).', is_string($data['name'] ?? null) ? $data['name'] : ''));
		}

		$type  = $data['type'] ?? FieldType::Text->value;
		$class = $data['class'] ?? null;
		$class = is_string($class) && is_subclass_of($class, Field::class)
			? $class
			: (is_string($type) ? $this->registry->get($type) : null);

		if ($class === null && $type === 'reference') {
			throw new InvalidSchema(sprintf(
				'Field "%s" is a reference, which isn\'t a field type; entries are linked by relationships (Structure → Relationships, or user/data/relations).',
				is_string($data['name'] ?? null) ? $data['name'] : ''
			));
		}

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
	 * Builds fields from definitions as a field set's file writes them,
	 * with the controls chosen for them (`ControlFactory::split()`, D-795).
	 *
	 * @param  array<array-key, mixed> $definitions
	 * @return array{list<Field>, FieldControls}
	 * @throws InvalidSchema
	 */
	public function withControls(array $definitions): array
	{
		[$definitions, $controls] = $this->controls->split($definitions);
		$fields                   = array_map($this->fromArray(...), $definitions);

		self::check($fields, $controls, '');

		return [$fields, $controls];
	}

	/**
	 * Checks each chosen control can draw its field: one that fits it, or
	 * its type's default (D-768).
	 *
	 * @param  iterable<Field> $fields
	 * @throws InvalidSchema
	 */
	private static function check(iterable $fields, FieldControls $controls, string $parent): void
	{
		foreach ($fields as $field) {
			$path    = FieldControls::path($parent, $field->name);
			$control = $controls->at($path);

			if ($control !== null && ! $control->fits($field) && $control::name() !== ControlDefaults::core($field)?->name()) {
				throw new InvalidSchema(sprintf('Field "%s" (%s) can\'t be edited with the "%s" control.', $field->name, $field->type(), $control::name()));
			}

			if ($field instanceof ObjectField) {
				self::check($field->schema->fields, $controls, $path);
			}

			if ($field instanceof ListField) {
				self::check([$field->item->named(FieldControls::ITEM)], $controls, $path);
			}
		}
	}

	/**
	 * Nests definitions as the admin's forms send them into the shape a
	 * field set's file writes (`ControlFactory::nest()`).
	 *
	 * @param  array<array-key, mixed> $definitions
	 * @return list<array<array-key, mixed>>
	 * @throws InvalidSchema
	 */
	public function nest(array $definitions): array
	{
		return $this->controls->nest($definitions);
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
