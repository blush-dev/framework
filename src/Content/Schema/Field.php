<?php

/**
 * Schema field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

use NoDiscard;

/**
 * The abstract base of every field type (the enum + registry pattern,
 * D-019). A field turns a raw front matter value into a normalized,
 * exportable one for the index (`normalize()`), and that back into the
 * typed value entries expose (`hydrate()`).
 *
 * Fields are immutable. The shared settings are set with fluent copies:
 *
 *     new DateField('published')->aliases('date')->required()
 *
 * A field's aliases are other keys it's read from, such as the 1.x names
 * (D-078). When the canonical key is present too, it wins.
 */
abstract class Field
{
	/**
	 * The front matter key. List items and nested fields may leave it
	 * empty.
	 */
	public protected(set) string $name = '';

	/**
	 * Other keys the value may be read from.
	 *
	 * @var list<string>
	 */
	public protected(set) array $aliases = [];

	/**
	 * Whether a value must be present.
	 */
	public protected(set) bool $required = false;

	/**
	 * The raw value used when none is given. It is normalized like any
	 * other value.
	 */
	public protected(set) mixed $default = null;

	/**
	 * A human-readable name, for the admin.
	 */
	public protected(set) string $label = '';

	/**
	 * Help text, for the admin.
	 */
	public protected(set) string $description = '';

	/**
	 * Returns the field type's registry key, such as `text`.
	 */
	abstract public function type(): string;

	/**
	 * Validates a raw value and returns its normalized form: plain data
	 * that `var_export()` can write into the index. `Schema` never passes
	 * `null`, `""`, or `[]`; it treats those as missing values.
	 *
	 * @throws InvalidField
	 */
	abstract public function normalize(mixed $value, FieldContext $context): mixed;

	/**
	 * Returns the typed value for a normalized one. Most fields store what
	 * they expose, so the default returns it unchanged.
	 */
	public function hydrate(mixed $value, FieldContext $context): mixed
	{
		return $value;
	}

	/**
	 * Builds the field from a definition array, as written in a data-defined
	 * content type. The factory builds any nested fields.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	abstract public static function fromArray(array $data, FieldFactory $factory): static;

	/**
	 * Returns a JSON Schema for the values this field accepts, for the
	 * editor schemas (D-211): the type's `valueType()` with the field's
	 * label, description, and default.
	 *
	 * @return array<string, mixed>
	 */
	public function valueSchema(): array
	{
		$description = trim($this->label . ($this->label !== '' && $this->description !== '' ? ': ' : '') . $this->description);

		return [
			...$this->valueType(),
			...($description === '' ? [] : ['description' => $description]),
			...($this->default === null ? [] : ['default' => $this->default])
		];
	}

	/**
	 * Returns the JSON Schema for the type's values, without the shared
	 * settings. It may be looser than `normalize()`, never stricter. The
	 * default accepts anything.
	 *
	 * @return array<string, mixed>
	 */
	protected function valueType(): array
	{
		return [];
	}

	/**
	 * Returns JSON Schemas for the type's own definition keys (`options`,
	 * `min`), by key, for the editor schemas in `resources/schemas`
	 * (D-206). The shared keys are described once for every type. A type
	 * may also narrow `default`.
	 *
	 * @param  array<string, mixed> $field A schema for a whole field definition, for types that nest fields.
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitionSchema(array $field): array
	{
		return [];
	}

	/**
	 * Returns the field as a definition array that `fromArray()` accepts.
	 * Unset shared settings are left out. A field class that isn't the
	 * built-in for its type also records its `class`, so a compiled cache
	 * can rebuild it without the registry that knew it.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return array_filter(
			[
				'name'        => $this->name,
				'type'        => $this->type(),
				'class'       => FieldType::tryFrom($this->type())?->className() === static::class ? null : static::class,
				'aliases'     => $this->aliases,
				'required'    => $this->required ?: null,
				'default'     => $this->default,
				'label'       => $this->label,
				'description' => $this->description,
				...$this->options()
			],
			static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null
		);
	}

	/**
	 * Returns a copy with another name.
	 */
	#[NoDiscard]
	public function named(string $name): static
	{
		return clone($this, ['name' => $name]);
	}

	/**
	 * Returns a copy that's also read from these keys.
	 */
	#[NoDiscard]
	public function aliases(string ...$aliases): static
	{
		return clone($this, ['aliases' => array_values(array_unique([...$this->aliases, ...$aliases]))]);
	}

	/**
	 * Returns a copy that must, or needn't, have a value.
	 */
	#[NoDiscard]
	public function required(bool $required = true): static
	{
		return clone($this, ['required' => $required]);
	}

	/**
	 * Returns a copy with a default raw value.
	 */
	#[NoDiscard]
	public function default(mixed $value): static
	{
		return clone($this, ['default' => $value]);
	}

	/**
	 * Returns a copy with a label.
	 */
	#[NoDiscard]
	public function labeled(string $label): static
	{
		return clone($this, ['label' => $label]);
	}

	/**
	 * Returns a copy with help text.
	 */
	#[NoDiscard]
	public function described(string $description): static
	{
		return clone($this, ['description' => $description]);
	}

	/**
	 * Returns the type-specific settings for `toArray()`.
	 *
	 * @return array<string, mixed>
	 */
	protected function options(): array
	{
		return [];
	}

	/**
	 * Applies the shared settings in a definition array to a new field.
	 *
	 * @template T of Field
	 * @param    T $field
	 * @return   T
	 * @throws   InvalidSchema
	 */
	protected static function withShared(Field $field, Definition $definition): Field
	{
		$field->aliases     = $definition->strings('aliases');
		$field->required    = $definition->bool('required');
		$field->default     = $definition->raw('default');
		$field->label       = $definition->string('label');
		$field->description = $definition->string('description');

		return $field;
	}

	/**
	 * Wraps a definition array for typed reads.
	 *
	 * @param array<array-key, mixed> $data
	 */
	protected static function definition(array $data): Definition
	{
		$name = $data['name'] ?? '';

		return new Definition($data, sprintf('Field "%s"', is_string($name) ? $name : ''));
	}

	/**
	 * Builds the exception for a value that doesn't fit.
	 */
	protected function invalid(string $message): InvalidField
	{
		return new InvalidField($message);
	}

	/**
	 * Describes a value's type for error messages.
	 */
	protected static function describe(mixed $value): string
	{
		return match (true) {
			is_array($value) => array_is_list($value) ? 'a list' : 'a map',
			is_bool($value)  => $value ? 'true' : 'false',
			default          => get_debug_type($value)
		};
	}
}
