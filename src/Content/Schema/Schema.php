<?php

/**
 * Schema.
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
 * An ordered set of fields that front matter (or any map) is resolved
 * against. Resolving:
 *
 * - reads each field from its name or, failing that, its first alias with
 *   a value, so `published` wins over the 1.x `date` (D-078);
 * - treats `null` (an empty YAML value), `""`, and `[]` as missing, as 1.x
 *   did (`image: ""` means no image);
 * - normalizes each value, reporting values that don't fit as errors
 *   rather than throwing;
 * - applies defaults and reports missing required fields; and
 * - keeps undeclared keys as extra values, reported as notices, or as
 *   errors when the schema is closed (D-081).
 *
 * Aliases in use are reported as notices, so `content:lint --strict` can
 * point at the canonical names.
 */
final readonly class Schema
{
	/**
	 * The fields, keyed by name.
	 *
	 * @var array<string, Field>
	 */
	public array $fields;

	/**
	 * Maps each field name and alias to its field's name.
	 *
	 * @var array<string, string>
	 */
	private array $keys;

	/**
	 * @param  iterable<Field> $fields
	 * @param  bool            $closed Whether undeclared keys are errors.
	 * @throws InvalidSchema When a name is missing or a name or alias is used twice.
	 */
	public function __construct(iterable $fields = [], public bool $closed = false)
	{
		$byName = [];
		$keys   = [];

		foreach ($fields as $field) {
			if ($field->name === '') {
				throw new InvalidSchema(sprintf('A schema field of type "%s" has no name.', $field->type()));
			}

			foreach ([$field->name, ...$field->aliases] as $key) {
				if (isset($keys[$key])) {
					throw new InvalidSchema(sprintf(
						'Schema key "%s" of field "%s" is already used by field "%s".',
						$key,
						$field->name,
						$keys[$key]
					));
				}

				$keys[$key] = $field->name;
			}

			$byName[$field->name] = $field;
		}

		$this->fields = $byName;
		$this->keys   = $keys;
	}

	/**
	 * Returns the field a key names, by name or alias.
	 */
	public function field(string $key): ?Field
	{
		$name = $this->keys[$key] ?? null;

		return $name === null ? null : $this->fields[$name];
	}

	/**
	 * Returns whether a field has this name.
	 */
	public function has(string $name): bool
	{
		return isset($this->fields[$name]);
	}

	/**
	 * Returns a copy with fields added, replacing any with the same name.
	 *
	 * @throws InvalidSchema
	 */
	#[NoDiscard]
	public function with(Field ...$fields): self
	{
		$merged = $this->fields;

		foreach ($fields as $field) {
			$merged[$field->name] = $field;
		}

		return new self($merged, $this->closed);
	}

	/**
	 * Returns a copy with another schema's fields added over these. The
	 * result is closed if either schema is.
	 *
	 * @throws InvalidSchema
	 */
	#[NoDiscard]
	public function merge(Schema $other): self
	{
		return new self([...$this->with(...array_values($other->fields))->fields], $this->closed || $other->closed);
	}

	/**
	 * Resolves raw data against the schema.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public function resolve(array $data, FieldContext $context): SchemaResult
	{
		$values     = [];
		$extra      = [];
		$violations = [];
		$seen       = [];

		foreach ($data as $key => $value) {
			$key  = (string) $key;
			$name = $this->keys[$key] ?? null;

			if ($name === null) {
				$extra[$key]  = $value;
				$violations[] = $this->closed
					? new Violation($key, 'is not a field of this type.')
					: new Violation($key, 'is not declared by the schema.', Severity::Notice);

				continue;
			}

			if (self::isEmpty($value)) {
				continue;
			}

			if ($key !== $name) {
				if (isset($seen[$name]) || ! self::isEmpty($data[$name] ?? null)) {
					$violations[] = new Violation($key, sprintf('is ignored because "%s" is set.', $name), Severity::Notice);

					continue;
				}

				$violations[] = new Violation($key, sprintf('is read as "%s".', $name), Severity::Notice);
			}

			$seen[$name] = true;

			try {
				$normalized = $this->fields[$name]->normalize($value, $context);
			} catch (InvalidField $e) {
				$violations[] = new Violation($key, $e->getMessage());

				continue;
			}

			if ($normalized !== null) {
				$values[$name] = $normalized;
			}
		}

		foreach ($this->fields as $name => $field) {
			if (isset($seen[$name])) {
				continue;
			}

			if ($field->default !== null) {
				try {
					$values[$name] = $field->normalize($field->default, $context);
				} catch (InvalidField $e) {
					$violations[] = new Violation($name, sprintf('has an invalid default: %s', $e->getMessage()));
				}
			} elseif ($field->required) {
				$violations[] = new Violation($name, 'is required.');
			}
		}

		return new SchemaResult($values, $extra, $violations);
	}

	/**
	 * Returns typed values for normalized ones. Values without a field
	 * pass through unchanged.
	 *
	 * @template K of array-key
	 * @param    array<K, mixed> $values
	 * @return   array<K, mixed>
	 */
	public function hydrate(array $values, FieldContext $context): array
	{
		foreach ($values as $name => $value) {
			$field = $this->fields[(string) $name] ?? null;

			if ($field !== null) {
				$values[$name] = $field->hydrate($value, $context);
			}
		}

		return $values;
	}

	/**
	 * Returns the schema as a definition array that `FieldFactory::schema()`
	 * accepts.
	 *
	 * @return array{fields: list<array<string, mixed>>, closed?: true}
	 */
	public function toArray(): array
	{
		$data = ['fields' => array_values(array_map(static fn (Field $field): array => $field->toArray(), $this->fields))];

		return $this->closed ? [...$data, 'closed' => true] : $data;
	}

	/**
	 * Returns whether a raw value counts as missing.
	 */
	private static function isEmpty(mixed $value): bool
	{
		return $value === null || $value === '' || $value === [];
	}
}
