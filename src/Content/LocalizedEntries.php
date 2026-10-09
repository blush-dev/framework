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

use DateTimeInterface;
use Override;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Type\ContentType;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;

/**
 * A repository whose default language is a page's (D-458): what a
 * directive or component on a translated page is given, so its queries, `named()`,
 * and `term()` find that language's entries without asking. A query or
 * lookup that names a language (or `Query::ANY_LANGUAGE`) keeps it.
 * Everything else is the wrapped repository's.
 */
final readonly class LocalizedEntries implements Entries
{
	public function __construct(
		private Entries $content,
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
	public function neighbors(Entry $entry, ?Query $query = null): array
	{
		return $this->content->neighbors($entry, $query === null ? null : $this->localized($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function termCounts(string|array $taxonomy, ?Query $query = null): array
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
	 * @inheritDoc
	 */
	#[Override]
	public function editable(Entry|string $entry): EditableEntry
	{
		return $this->content->editable($entry);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function editableAt(ContentType $type, string $key): ?EditableEntry
	{
		return $this->content->editableAt($type, $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function create(ContentType $type, string $slug, EntryChanges $changes, Entry|string|null $parent = null, ?DateTimeInterface $date = null): Entry
	{
		return $this->content->create($type, $slug, $changes, $parent, $date);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes): Entry
	{
		return $this->content->createAt($type, $key, $changes);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(Entry|string $entry, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): Entry
	{
		return $this->content->duplicate($entry, $slug, $changes, $date);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function change(Entry|string $entry, EntryChanges $changes, ?string $version = null): Entry
	{
		return $this->content->change($entry, $changes, $version);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(Entry|string $entry, string $slug, ?string $version = null): Entry
	{
		return $this->content->rename($entry, $slug, $version);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(Entry|string $entry, Entry|string|null $parent, ?string $version = null): Entry
	{
		return $this->content->move($entry, $parent, $version);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(Entry|string $entry, ?string $version = null): Entry
	{
		return $this->content->trash($entry, $version);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(Entry|string $entry, ?string $version = null, ?Status $status = Status::Draft): Entry
	{
		return $this->content->restore($entry, $version, $status);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Entry|string $entry, ?string $version = null): void
	{
		$this->content->delete($entry, $version);
	}

	/**
	 * Returns a query in the language unless it names one.
	 */
	private function localized(Query $query): Query
	{
		return $query->language === null ? $query->language($this->language) : $query;
	}
}
