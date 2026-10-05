<?php

/**
 * Entry collection.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Content\Entry\Entry;

/**
 * The entries a query found, in order. Entries are built from the index
 * the first time they're needed, and bodies only when read, so a listing
 * that shows titles never renders one.
 *
 * `count()` is the entries here; `total()` is how many matched before the
 * query's limit and offset.
 *
 * @implements IteratorAggregate<int, Entry>
 */
final class EntryCollection implements IteratorAggregate, Countable
{
	/**
	 * @var ?list<Entry>
	 */
	private ?array $entries = null;

	/**
	 * @param list<string>                              $paths The entries' paths, in order.
	 * @param int                                       $total How many matched in all.
	 * @param ?Closure(list<string>): list<Entry>       $load  Builds the entries for paths.
	 */
	public function __construct(
		public readonly array $paths = [],
		private readonly int $total = 0,
		private readonly ?Closure $load = null
	) {}

	/**
	 * Builds a collection of entries already in hand.
	 */
	public static function of(Entry ...$entries): self
	{
		$entries    = array_values($entries);
		$collection = new self(array_map(static fn (Entry $entry): string => $entry->path, $entries), count($entries));

		$collection->entries = $entries;

		return $collection;
	}

	/**
	 * Returns the entries.
	 *
	 * @return list<Entry>
	 */
	public function all(): array
	{
		return $this->entries ??= $this->load === null ? [] : ($this->load)($this->paths);
	}

	/**
	 * Returns the first entry, or `null`.
	 */
	public function first(): ?Entry
	{
		return array_first($this->all());
	}

	/**
	 * Returns the last entry, or `null`.
	 */
	public function last(): ?Entry
	{
		return array_last($this->all());
	}

	/**
	 * Returns whether there are no entries.
	 */
	public function isEmpty(): bool
	{
		return $this->paths === [];
	}

	/**
	 * Returns whether an entry with a slug is here.
	 */
	public function has(string $slug): bool
	{
		return array_any($this->all(), static fn (Entry $entry): bool => $entry->slug === $slug);
	}

	/**
	 * Returns how many entries matched before the limit and offset.
	 */
	public function total(): int
	{
		return $this->total;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->paths);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<int, Entry>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->all());
	}
}
