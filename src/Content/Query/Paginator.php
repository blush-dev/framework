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
use Closure;
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
	 * Returns numbered pagination for the listing, 1.x's style: the first
	 * and last `$endSize` pages, `$midSize` pages on each side of the
	 * current one, and dots for the pages left out. A gap of one page
	 * shows that page instead of dots. With `$adjacent`, the list starts
	 * with a previous link and ends with a next link, when there are
	 * such pages.
	 *
	 * `$url` builds each page's URL (a `ContentPage` passes its own, see
	 * `ContentPage::pageLinks()`). A listing with one page, or a page past
	 * the last, has no links.
	 *
	 * @param  ?Closure(int): ?string $url
	 * @return list<PageLink>
	 */
	public function links(?Closure $url = null, int $endSize = 1, int $midSize = 1, bool $adjacent = true): array
	{
		$pages = $this->pages();

		if ($pages < 2 || $this->isOutOfRange()) {
			return [];
		}

		$endSize = max(0, $endSize);
		$midSize = max(0, $midSize);
		$current = $this->page;
		$urlFor  = static fn (int $number): ?string => $url === null ? null : $url($number);
		$isShown = static fn (int $number): bool => $number <= $endSize
			|| $number > $pages - $endSize
			|| abs($number - $current) <= $midSize;

		$links    = [];
		$previous = $this->previous();

		if ($adjacent && $previous !== null) {
			$links[] = new PageLink(PageLinkKind::Previous, $previous, $urlFor($previous));
		}

		for ($number = 1; $number <= $pages; $number++) {
			$fillsGap = $number > 1 && $number < $pages && $isShown($number - 1) && $isShown($number + 1);

			if ($number === $current) {
				$links[] = new PageLink(PageLinkKind::Current, $number);
			} elseif ($isShown($number) || $fillsGap) {
				$links[] = new PageLink(PageLinkKind::Number, $number, $urlFor($number));
			} elseif (array_last($links)?->kind !== PageLinkKind::Dots) {
				$links[] = new PageLink(PageLinkKind::Dots);
			}
		}

		$next = $this->next();

		if ($adjacent && $next !== null) {
			$links[] = new PageLink(PageLinkKind::Next, $next, $urlFor($next));
		}

		return $links;
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
