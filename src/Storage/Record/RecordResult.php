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
 * The records a query found, within its limit and offset, and how many
 * it matched in all.
 *
 * @implements IteratorAggregate<int, Record>
 */
final readonly class RecordResult implements IteratorAggregate, Countable
{
	/**
	 * @param list<Record> $records
	 */
	public function __construct(
		public array $records,
		public int $total
	) {}

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
