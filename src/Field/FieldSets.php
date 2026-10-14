<?php

/**
 * Field sets.
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
 * The site's field sets (`FieldSetLoader`), by name, with the fields
 * code registers (D-790). A target's sets are the ones attached to it,
 * in name order.
 *
 * @implements IteratorAggregate<string, FieldSet>
 */
final readonly class FieldSets implements IteratorAggregate, Countable
{
	/**
	 * @var array<string, FieldSet>
	 */
	private array $sets;

	/**
	 * @param array<string, FieldSet> $sets Keyed by name.
	 */
	public function __construct(array $sets = [], public RegisteredFields $registered = new RegisteredFields())
	{
		ksort($sets, SORT_STRING);

		$this->sets = $sets;
	}

	/**
	 * Returns a set by name, if there is one.
	 */
	public function find(string $name): ?FieldSet
	{
		return $this->sets[$name] ?? null;
	}

	/**
	 * Returns every set, keyed by name, in name order.
	 *
	 * @return array<string, FieldSet>
	 */
	public function all(): array
	{
		return $this->sets;
	}

	/**
	 * Returns the sets attached to a target, in name order: those that
	 * name it, or every place of its kind.
	 *
	 * @return list<FieldSet>
	 */
	public function for(string $target): array
	{
		return array_values(array_filter($this->sets, static fn (FieldSet $set): bool => $set->attachesTo($target)));
	}

	/**
	 * Returns a target's full schema: its own fields (core's first, for a
	 * type), then the fields extensions register on it, then each set's,
	 * in set name order. A key is declared once (D-786): a field or set
	 * that would declare a key again, or that the target won't take, is
	 * left off it rather than stopping the site (D-789), and `clashes()`
	 * says why.
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
	 * the schema, so a set's field of the same name stays.
	 */
	public function drawn(Schema $schema, string $target): Schema
	{
		$fields = array_map(static fn (RegisteredField $field): Field => $field->field, $this->registered->for($target));

		return new Schema(array_filter($schema->fields, static fn (Field $field): bool => ! in_array($field, $fields, true)), $schema->closed);
	}

	/**
	 * Returns why what's attached to a target is left off it, written with
	 * `$messages` (D-836): by set name, or by the owner of a registered
	 * field (for `content:lint`, the site's checks, and the admin).
	 *
	 * @return array<string, list<Text>>
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	public function clashes(FieldTarget $target, Messages $messages): array
	{
		return $this->resolve($target, $messages)[1];
	}

	/**
	 * Attaches a target's registered fields and sets one at a time,
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

		$attachments = [
			...array_map(static fn (RegisteredField $field): array => [$field->owner, $messages->t('field.field_sets.registered_field', '{owner}\'s field "{field}"', owner: $field->owner, field: $field->field->name), [$field->field]], $this->registered->for($target->key())),
			...array_map(static fn (FieldSet $set): array => [$set->name, $messages->t('field.field_sets.field_set', 'field set "{set}"', set: $set->name), array_values($set->schema->fields)], $this->for($target->key()))
		];

		foreach ($attachments as [$name, $what, $fields]) {
			$attached = $this->attach($schema, $target, $what, $fields, $owners, $messages);

			if ($attached instanceof Text) {
				$clashes[$name][] = $attached;

				continue;
			}

			$schema = $attached;

			foreach ($fields as $field) {
				foreach ([$field->name, ...$field->aliases] as $key) {
					$owners[$key] = $what;
				}
			}
		}

		return [$schema, $clashes];
	}

	/**
	 * Adds fields to a target's schema, or returns why they can't be
	 * added, naming the target, what's attached, and what declared the
	 * key first.
	 *
	 * @param list<Field>          $fields
	 * @param array<string, ?Text> $owners What declared keys before, by key; `null` for core.
	 */
	private function attach(Schema $schema, FieldTarget $target, Text $what, array $fields, array $owners, Messages $messages): Schema|Text
	{
		foreach ($fields as $field) {
			if (! $target->accepts($field)) {
				return $messages->t('field.field_sets.not_taken', '{target} can\'t take {what}: it takes no field named "{field}".', target: $target->key(), what: $what, field: $field->name);
			}

			foreach ([$field->name, ...$field->aliases] as $key) {
				if ($schema->field($key) === null) {
					continue;
				}

				return match (true) {
					! array_key_exists($key, $owners) => $messages->t('field.field_sets.own_field', '{target} can\'t take {what}: "{name}" is already one of its own fields.', target: $target->key(), what: $what, name: $key),
					$owners[$key] === null            => $messages->t('field.field_sets.core_key', '{target} can\'t take {what}: "{name}" is Blush\'s own key, so nothing else can declare it.', target: $target->key(), what: $what, name: $key),
					default                           => $messages->t('field.field_sets.declared', '{target} can\'t take {what}: {owner} declares "{name}" there already, and a key is declared once.', target: $target->key(), what: $what, owner: $owners[$key], name: $key)
				};
			}
		}

		try {
			return new Schema([...array_values($schema->fields), ...$fields], $schema->closed);
		} catch (InvalidSchema $e) {
			return $messages->t('field.field_sets.invalid', '{target} can\'t take {what}: {problem}', target: $target->key(), what: $what, problem: $e->getMessage());
		}
	}

	/**
	 * Returns the sets as an array for a compiled cache.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArray(): array
	{
		return array_values(array_map(static fn (FieldSet $set): array => $set->toArray(), $this->sets));
	}

	/**
	 * Rebuilds the sets from `toArray()`'s output, with the registered
	 * fields.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory, RegisteredFields $registered = new RegisteredFields()): self
	{
		$sets = [];

		foreach ($data as $definition) {
			if (is_array($definition)) {
				$set              = FieldSet::fromArray($definition, $factory);
				$sets[$set->name] = $set;
			}
		}

		return new self($sets, $registered);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, FieldSet>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->sets);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->sets);
	}
}
