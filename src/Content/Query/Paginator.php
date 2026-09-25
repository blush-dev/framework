<?php

/**
 * Paginator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Content\Entry\Entry;

/**
 * One page of a query's entries, with what pagination links need. A page
 * past the last one is empty; controllers turn that into a 404.
 *
 * @implements IteratorAggregate<int, Entry>
 */
final readonly class Paginator implements IteratorAggregate, Countable
{
	/**
	 * The page number, from 1.
	 */
	public int $page;

	/**
	 * How many entries a page holds, at least 1.
	 */
	public int $perPage;

	public function __construct(
		public EntryCollection $entries,
		int $perPage,
		int $page = 1
	) {
		$this->perPage = max(1, $perPage);
		$this->page    = max(1, $page);
	}

	/**
	 * Returns how many entries matched in all.
	 */
	public function total(): int
	{
		return $this->entries->total();
	}

	/**
	 * Returns how many pages there are (at least 1, so an empty listing
	 * still has its first page).
	 */
	public function pages(): int
	{
		return max(1, (int) ceil($this->total() / $this->perPage));
	}

	/**
	 * Returns whether this page is past the last one.
	 */
	public function isOutOfRange(): bool
	{
		return $this->page > $this->pages();
	}

	/**
	 * Returns whether a later page exists.
	 */
	public function hasNext(): bool
	{
		return $this->page < $this->pages();
	}

	/**
	 * Returns whether an earlier page exists.
	 */
	public function hasPrevious(): bool
	{
		return $this->page > 1;
	}

	/**
	 * Returns the next page's number, or `null`.
	 */
	public function next(): ?int
	{
		return $this->hasNext() ? $this->page + 1 : null;
	}

	/**
	 * Returns the previous page's number, or `null`.
	 */
	public function previous(): ?int
	{
		return $this->hasPrevious() ? min($this->page - 1, $this->pages()) : null;
	}

	/**
	 * Returns the page's entries.
	 *
	 * @return list<Entry>
	 */
	public function all(): array
	{
		return $this->entries->all();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->entries);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<int, Entry>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return $this->entries->getIterator();
	}
}
