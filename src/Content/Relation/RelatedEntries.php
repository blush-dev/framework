<?php

/**
 * Related entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use NoDiscard;
use Override;
use Traversable;
use Blush\Content\Entry\Entry;

/**
 * The entries one entry leads to through a relation (D-803), as
 * `$entry->related()` and `$entry->referencedBy()` give them: a list to
 * loop over and count, with the relation that names them (its `label`
 * and `singular`, for a heading) and whether that's the entry type's
 * byline. They come whatever their status (D-802); `published()` keeps
 * the ones a page may show and link.
 *
 * @implements IteratorAggregate<int, Entry>
 */
final class RelatedEntries implements Countable, IteratorAggregate
{
	/**
	 * The published ones, once asked for (D-805).
	 */
	private ?self $published = null;

	/**
	 * @param list<Entry> $entries
	 * @param ?Relation   $relation The relation, or `null` when the name matches none.
	 * @param bool        $byline   Whether the relation is the entry type's byline (D-602).
	 */
	public function __construct(
		private readonly array $entries = [],
		public readonly ?Relation $relation = null,
		public readonly bool $byline = false
	) {}

	/**
	 * Returns the ones that are published and have a page of their own,
	 * worked out once.
	 */
	#[NoDiscard]
	public function published(): self
	{
		return $this->published ??= new self(
			array_values(array_filter($this->entries, static fn (Entry $entry): bool => $entry->isLinkable())),
			$this->relation,
			$this->byline
		);
	}

	/**
	 * Returns the first, or `null` when there are none.
	 */
	public function first(): ?Entry
	{
		return array_first($this->entries);
	}

	/**
	 * Returns whether there are none.
	 */
	public function isEmpty(): bool
	{
		return $this->entries === [];
	}

	/**
	 * Returns them as a list.
	 *
	 * @return list<Entry>
	 */
	public function all(): array
	{
		return $this->entries;
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
	 * @return Traversable<int, Entry>
	 */
	#[Override]
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->entries);
	}
}
