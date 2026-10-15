<?php

/**
 * Field library.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The site's field library (D-858), by name, with the fields code
 * registers (D-790). A place carries the library fields its layout
 * names (`FieldTarget::layout()`); a field never chooses its places.
 *
 * @implements IteratorAggregate<string, LibraryField>
 */
final readonly class FieldLibrary implements IteratorAggregate, Countable
{
	/**
	 * @var array<string, LibraryField>
	 */
	private array $fields;

	/**
	 * @param array<string, LibraryField> $fields Keyed by name.
	 */
	public function __construct(array $fields = [], public RegisteredFields $registered = new RegisteredFields())
	{
		ksort($fields, SORT_STRING);

		$this->fields = $fields;
	}

	/**
	 * Returns a field by name, if the library has it.
	 */
	public function find(string $name): ?LibraryField
	{
		return $this->fields[$name] ?? null;
	}

	/**
	 * Returns every field, keyed by name, in name order.
	 *
	 * @return array<string, LibraryField>
	 */
	public function all(): array
	{
		return $this->fields;
	}

	/**
	 * Returns the library fields a layout places, in its order, each as
	 * the place asks for it (`PlacedField::apply()`), keyed by name. A
	 * name the library doesn't have is left out (`content:lint` notes
	 * it).
	 *
	 * @return array<string, Field>
	 */
	public function placed(FieldLayout $layout): array
	{
		$fields = [];

		foreach ($layout->fields() as $placed) {
			$field = $this->find($placed->name);

			if ($field !== null) {
				$fields[$placed->name] = $placed->apply($field->field);
			}
		}

		return $fields;
	}

	/**
	 * Returns the controls a layout's fields are drawn with, by path.
	 */
	public function controls(FieldLayout $layout): FieldControls
	{
		$controls = [];

		foreach ($layout->names() as $name) {
			$controls = [...$controls, ...$this->find($name)?->controls->controls ?? []];
		}

		return new FieldControls($controls);
	}

	/**
	 * Returns a target's full schema: its own fields (core's first, for a
	 * type), then the fields extensions register on it, then its
	 * layout's library fields, in its order. A key is declared once
	 * (D-786): a field that would declare a key again, or that the target
	 * won't take, is left off it rather than stopping the site (D-789),
	 * and `clashes()` says why.
	 *
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	public function schemaFor(FieldTarget $target): Schema
	{
		return $this->resolve($target, new Messages())[0];
	}

	/**
	 * Returns a target's schema as the admin's forms draw it: its full
	 * schema (`schemaFor()`) without the fields extensions register, which
	 * no form shows on its own (D-791). Their values are kept, since a
	 * save changes only what it's sent. One left off for a clash isn't in
	 * the schema, so a library field of the same name stays.
	 */
	public function drawn(Schema $schema, string $target): Schema
	{
		$fields = array_map(static fn (RegisteredField $field): Field => $field->field, $this->registered->for($target));

		return new Schema(array_filter($schema->fields, static fn (Field $field): bool => ! in_array($field, $fields, true)), $schema->closed);
	}

	/**
	 * Returns why what a target carries is left off it, written with
	 * `$messages` (D-836): by library field name, or by the owner of a
	 * registered field (for `content:lint`, the site's checks, and the
	 * admin).
	 *
	 * @return array<string, list<Text>>
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	public function clashes(FieldTarget $target, Messages $messages): array
	{
		return $this->resolve($target, $messages)[1];
	}

	/**
	 * Adds a target's registered fields and library fields one at a time,
	 * leaving off each that doesn't fit what's before it.
	 *
	 * @return array{Schema, array<string, list<Text>>}
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	private function resolve(FieldTarget $target, Messages $messages): array
	{
		$schema  = $target->schema();
		$owners  = [];
		$clashes = [];

		foreach ($this->registered->core($target->key()) as $field) {
			foreach ([$field->name, ...$field->aliases] as $key) {
				$owners[$key] = null;
			}
		}

		$additions = [
			...array_map(static fn (RegisteredField $field): array => [$field->owner, $messages->t('field.field_library.registered_field', '{owner}\'s field "{field}"', owner: $field->owner, field: $field->field->name), $field->field], $this->registered->for($target->key())),
			...array_map(static fn (string $name, Field $field): array => [$name, $messages->t('field.field_library.library_field', 'the "{field}" field', field: $name), $field], array_keys($placed = $this->placed($target->layout())), $placed)
		];

		foreach ($additions as [$name, $what, $field]) {
			$added = $this->add($schema, $target, $what, $field, $owners, $messages);

			if ($added instanceof Text) {
				$clashes[$name][] = $added;

				continue;
			}

			$schema = $added;

			foreach ([$field->name, ...$field->aliases] as $key) {
				$owners[$key] = $what;
			}
		}

		return [$schema, $clashes];
	}

	/**
	 * Adds a field to a target's schema, or returns why it can't be
	 * added, naming the target, the field, and what declared the key
	 * first.
	 *
	 * @param array<string, ?Text> $owners What declared keys before, by key; `null` for core.
	 */
	private function add(Schema $schema, FieldTarget $target, Text $what, Field $field, array $owners, Messages $messages): Schema|Text
	{
		if (! $target->accepts($field)) {
			return $messages->t('field.field_library.not_taken', '{target} can\'t take {what}: it takes no field named "{field}".', target: $target->key(), what: $what, field: $field->name);
		}

		foreach ([$field->name, ...$field->aliases] as $key) {
			if ($schema->field($key) === null) {
				continue;
			}

			return match (true) {
				! array_key_exists($key, $owners) => $messages->t('field.field_library.own_field', '{target} can\'t take {what}: "{name}" is already one of its own fields.', target: $target->key(), what: $what, name: $key),
				$owners[$key] === null            => $messages->t('field.field_library.core_key', '{target} can\'t take {what}: "{name}" is Blush\'s own key, so nothing else can declare it.', target: $target->key(), what: $what, name: $key),
				default                           => $messages->t('field.field_library.declared', '{target} can\'t take {what}: {owner} declares "{name}" there already, and a key is declared once.', target: $target->key(), what: $what, owner: $owners[$key], name: $key)
			};
		}

		try {
			return new Schema([...array_values($schema->fields), $field], $schema->closed);
		} catch (InvalidSchema $e) {
			return $messages->t('field.field_library.invalid', '{target} can\'t take {what}: {problem}', target: $target->key(), what: $what, problem: $e->getMessage());
		}
	}

	/**
	 * Returns the library as an array for a compiled cache.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArray(): array
	{
		return array_values(array_map(static fn (LibraryField $field): array => $field->toArray(), $this->fields));
	}

	/**
	 * Rebuilds the library from `toArray()`'s output, with the registered
	 * fields.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory, RegisteredFields $registered = new RegisteredFields()): self
	{
		$fields = [];

		foreach ($data as $definition) {
			if (is_array($definition)) {
				$field                  = LibraryField::fromArray($definition, $factory);
				$fields[$field->name()] = $field;
			}
		}

		return new self($fields, $registered);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, LibraryField>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->fields);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->fields);
	}
}
