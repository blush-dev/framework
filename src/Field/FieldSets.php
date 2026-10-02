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
 * The site's resolved field sets, from every source (`FieldSetLoader`),
 * by name, with where each was defined. A target's sets are the ones
 * attached to it, in name order.
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
	 * @param array<string, FieldSet>       $sets    Keyed by name.
	 * @param array<string, FieldSetOrigin> $origins Keyed by name.
	 */
	public function __construct(array $sets = [], private array $origins = [])
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
	 * Returns where a set was defined.
	 */
	public function origin(string $name): FieldSetOrigin
	{
		return $this->origins[$name] ?? FieldSetOrigin::Extension;
	}

	/**
	 * Returns the sets attached to a target, in name order.
	 *
	 * @return list<FieldSet>
	 */
	public function for(string $target): array
	{
		return array_values(array_filter($this->sets, static fn (FieldSet $set): bool => $set->attachesTo($target)));
	}

	/**
	 * Returns a target's full schema: its own fields, then the fields of
	 * each set attached to it, in set name order. A set's field may not
	 * reuse a name or alias before it, and the target must take it.
	 *
	 * @throws InvalidSchema Naming the target and the set.
	 */
	public function schemaFor(FieldTarget $target): Schema
	{
		$schema = $target->schema();

		foreach ($this->for($target->key()) as $set) {
			$schema = $this->attach($schema, $target, $set);
		}

		return $schema;
	}

	/**
	 * Returns why each set attached to a target doesn't fit it, by set
	 * name, trying every set rather than stopping at the first (for
	 * `content:lint`). A set that doesn't fit is left out of the ones
	 * after it.
	 *
	 * @return array<string, string>
	 * @throws InvalidSchema When the target's own fields don't fit.
	 */
	public function clashes(FieldTarget $target): array
	{
		$schema  = $target->schema();
		$clashes = [];

		foreach ($this->for($target->key()) as $set) {
			try {
				$schema = $this->attach($schema, $target, $set);
			} catch (InvalidSchema $e) {
				$clashes[$set->name] = $e->getMessage();
			}
		}

		return $clashes;
	}

	/**
	 * Adds a set's fields to a target's schema.
	 *
	 * @throws InvalidSchema
	 */
	private function attach(Schema $schema, FieldTarget $target, FieldSet $set): Schema
	{
		foreach ($set->schema->fields as $field) {
			if (! $target->accepts($field)) {
				throw new InvalidSchema(sprintf('%s doesn\'t take field set "%s" field "%s".', $target->key(), $set->name, $field->name));
			}
		}

		try {
			return new Schema([...array_values($schema->fields), ...array_values($set->schema->fields)], $schema->closed);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('%s can\'t take field set "%s": %s', $target->key(), $set->name, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the sets as an array for a compiled cache.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function toArray(): array
	{
		$sets = [];

		foreach ($this->sets as $name => $set) {
			$sets[] = [...$set->toArray(), 'origin' => $this->origin($name)->value];
		}

		return $sets;
	}

	/**
	 * Rebuilds the sets from `toArray()`'s output.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory): self
	{
		$sets    = [];
		$origins = [];

		foreach ($data as $definition) {
			if (! is_array($definition)) {
				continue;
			}

			$origin = $definition['origin'] ?? null;
			unset($definition['origin']);

			$set                 = FieldSet::fromArray($definition, $factory);
			$sets[$set->name]    = $set;
			$origins[$set->name] = FieldSetOrigin::tryFrom(is_string($origin) ? $origin : '') ?? FieldSetOrigin::Extension;
		}

		return new self($sets, $origins);
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
