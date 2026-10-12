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
		return $this->resolve($target)[0];
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
	 * Returns why what's attached to a target is left off it: by set
	 * name, or by the owner of a registered field (for `content:lint`,
	 * the site's checks, and the admin).
	 *
	 * @return array<string, list<string>>
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	public function clashes(FieldTarget $target): array
	{
		return $this->resolve($target)[1];
	}

	/**
	 * Attaches a target's registered fields and sets one at a time,
	 * leaving off each that doesn't fit what's before it.
	 *
	 * @return array{Schema, array<string, list<string>>}
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	private function resolve(FieldTarget $target): array
	{
		$schema  = $target->schema();
		$owners  = [];
		$clashes = [];

		foreach ($this->registered->core($target->key()) as $field) {
			foreach ([$field->name, ...$field->aliases] as $key) {
				$owners[$key] = RegisteredField::CORE;
			}
		}

		$attachments = [
			...array_map(static fn (RegisteredField $field): array => [$field->owner, sprintf('%s\'s field "%s"', $field->owner, $field->field->name), [$field->field]], $this->registered->for($target->key())),
			...array_map(static fn (FieldSet $set): array => [$set->name, sprintf('field set "%s"', $set->name), array_values($set->schema->fields)], $this->for($target->key()))
		];

		foreach ($attachments as [$name, $what, $fields]) {
			try {
				$schema = $this->attach($schema, $target, $what, $fields, $owners);
			} catch (InvalidSchema $e) {
				$clashes[$name][] = $e->getMessage();

				continue;
			}

			foreach ($fields as $field) {
				foreach ([$field->name, ...$field->aliases] as $key) {
					$owners[$key] = $what;
				}
			}
		}

		return [$schema, $clashes];
	}

	/**
	 * Adds fields to a target's schema.
	 *
	 * @param  list<Field>           $fields
	 * @param  array<string, string> $owners What declared keys before, by key.
	 * @throws InvalidSchema Naming the target, what's attached, and what declared the key first.
	 */
	private function attach(Schema $schema, FieldTarget $target, string $what, array $fields, array $owners): Schema
	{
		foreach ($fields as $field) {
			if (! $target->accepts($field)) {
				throw new InvalidSchema(sprintf('%s can\'t take %s: it takes no field named "%s".', $target->key(), $what, $field->name));
			}

			foreach ([$field->name, ...$field->aliases] as $key) {
				if ($schema->field($key) === null) {
					continue;
				}

				$owner = $owners[$key] ?? null;

				throw new InvalidSchema(match ($owner) {
					RegisteredField::CORE => sprintf('%s can\'t take %s: "%s" is Blush\'s own key, so nothing else can declare it.', $target->key(), $what, $key),
					null                  => sprintf('%s can\'t take %s: "%s" is already one of its own fields.', $target->key(), $what, $key),
					default               => sprintf('%s can\'t take %s: %s declares "%s" there already, and a key is declared once.', $target->key(), $what, $owner, $key)
				});
			}
		}

		try {
			return new Schema([...array_values($schema->fields), ...$fields], $schema->closed);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('%s can\'t take %s: %s', $target->key(), $what, $e->getMessage()), previous: $e);
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
