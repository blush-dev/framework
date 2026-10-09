<?php

/**
 * Record result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;

/**
 * The records a query found, within its limit and offset, how many it
 * matched in all, and, when it asked for them (`with()`), what each
 * refers to.
 *
 * @implements IteratorAggregate<int, Record>
 */
final readonly class RecordResult implements IteratorAggregate, Countable
{
	/**
	 * @param list<Record>                               $records
	 * @param array<string, array<string, list<string>>> $refs    Targets by record id, then relation, in order.
	 */
	public function __construct(
		public array $records,
		public int $total,
		public array $refs = []
	) {}

	/**
	 * Returns the ids a record refers to through a relation, in order,
	 * as loaded with `with()`.
	 *
	 * @return list<string>
	 */
	public function refs(string $id, string $relation): array
	{
		return $this->refs[strtolower($id)][$relation] ?? [];
	}

	/**
	 * Returns the result with refs loaded.
	 *
	 * @param array<string, array<string, list<string>>> $refs
	 */
	#[\NoDiscard]
	public function withRefs(array $refs): self
	{
		return new self($this->records, $this->total, $refs);
	}

	/**
	 * Returns the first record, or `null` when there's none.
	 */
	public function first(): ?Record
	{
		return $this->records[0] ?? null;
	}

	/**
	 * @return ArrayIterator<int, Record>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->records);
	}

	/**
	 * Returns how many records are here (not the total matched).
	 */
	#[Override]
	public function count(): int
	{
		return count($this->records);
	}
}
