<?php

/**
 * Localized content repository.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Override;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;

/**
 * A repository whose default language is a page's (D-458): what a
 * directive or component on a translated page is given, so its queries, `named()`,
 * and `term()` find that language's entries without asking. A query or
 * lookup that names a language (or `Query::ANY_LANGUAGE`) keeps it.
 * Everything else is the wrapped repository's.
 */
final readonly class LocalizedRepository implements ContentRepository
{
	public function __construct(
		private ContentRepository $content,
		public string $language
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function query(): Query
	{
		return new Query(runner: $this)->language($this->language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function get(Query $query): EntryCollection
	{
		return $this->content->get($this->localized($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function paginate(Query $query, int $perPage, int $page = 1): Paginator
	{
		return $this->content->paginate($this->localized($query), $perPage, $page);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Query $query): int
	{
		return $this->content->count($this->localized($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?Entry
	{
		return $this->content->find($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findPath(string $path): ?Entry
	{
		return $this->content->findPath($path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function named(string $type, string $key, ?string $language = null): ?Entry
	{
		return $this->content->named($type, $key, $language ?? $this->language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function term(string $taxonomy, string $slug, ?string $language = null): ?Entry
	{
		return $this->content->term($taxonomy, $slug, $language ?? $this->language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function translations(Entry $entry): array
	{
		return $this->content->translations($entry);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function translation(Entry $entry, string $language): ?Entry
	{
		return $this->content->translation($entry, $language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parentKey(string $type, string $key, ?string $language = null): ?string
	{
		return $this->content->parentKey($type, $key, $language ?? $this->language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parent(Entry $entry): ?Entry
	{
		return $this->content->parent($entry);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function children(Entry $entry): array
	{
		return $this->content->children($entry);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function termCounts(string $taxonomy, ?Query $query = null): array
	{
		return $this->content->termCounts($taxonomy, $this->localized($query ?? $this->query()));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function redirects(): array
	{
		return $this->content->redirects();
	}

	/**
	 * Returns a query in the language unless it names one.
	 */
	private function localized(Query $query): Query
	{
		return $query->language === null ? $query->language($this->language) : $query;
	}
}
