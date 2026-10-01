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
