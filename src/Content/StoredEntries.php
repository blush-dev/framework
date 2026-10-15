<?php

/**
 * Stored entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use DateTimeInterface;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Container\Attributes\Defer;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Entry\Position;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\QueryCompiler;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordResult;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Refs;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;
use Blush\Workflow\Status;

/**
 * The default `Entries` (D-654), on records (D-649), the same for every
 * driver: queries compile to record queries the content area's store
 * answers (D-652, D-653), entries are built from the records found, at
 * the keys and places the store's `EntryLocations` gives (by parents,
 * D-656), and writes go through the storage driver's `ContentWriter`,
 * each answering the entry as it is after. Writing a page at a fixed key
 * writes the parent pages its key names that don't exist yet, so its
 * key holds.
 *
 * The filesystem driver keeps its index up to date itself
 * (`IndexFreshness`).
 */
final class StoredEntries implements Entries
{
	/**
	 * @param Closure(): ContentWriter $writer
	 */
	public function __construct(
		private readonly EntryHydrator $hydrator,
		private readonly ContentTypes $types,
		private readonly AppConfig $app,
		private readonly ClockInterface $clock,
		private readonly QueryCompiler $compiler,
		private readonly RecordStores $stores,
		private readonly EntryLocations $locations,
		#[Defer(ContentWriter::class)] private readonly Closure $writer,
		private readonly Messages $messages,
		private readonly RequestMemo $memo = new RequestMemo()
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function query(): Query
	{
		return new Query(runner: $this);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function get(Query $query): EntryCollection
	{
		$compiled = $this->compile($query);

		return $this->memo->remember('collection|' . $compiled->fingerprint(), function () use ($compiled): EntryCollection {
			$found = $this->records($compiled);

			return new EntryCollection(self::ids($found->records), $found->total, fn (): array => $this->entries($found->records));
		});
	}

	/**
	 * Runs a query: compiled to a record query over `entries` (D-649),
	 * as of now, and answered by the store that keeps them (D-653), the
	 * records found without their content, which entries read lazily.
	 * The compiler resolves the query's language and 1.x arguments.
	 *
	 * @throws InvalidQuery
	 */
	private function select(Query $query): RecordResult
	{
		return $this->records($this->compile($query));
	}

	/**
	 * Compiles a query to a record query over `entries`, as of now,
	 * without content.
	 *
	 * @throws InvalidQuery
	 */
	private function compile(Query $query): RecordQuery
	{
		return $this->compiler->compile($query, $this->now(), $this->locations)->withoutContent();
	}

	/**
	 * Returns the records a record query over `entries` finds, asked of
	 * the store once a request (its reads are memoized, D-807).
	 */
	private function records(RecordQuery $query): RecordResult
	{
		return $this->store()->select(EntryTable::table(), $query);
	}


	/**
	 * @inheritDoc
	 */
	#[Override]
	public function paginate(Query $query, int $perPage, int $page = 1): Paginator
	{
		$perPage = max(1, $perPage);
		$page    = max(1, $page);

		return new Paginator($this->get($query->limit($perPage)->offset(($page - 1) * $perPage)), $perPage, $page);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Query $query): int
	{
		return $this->select($query->limit(0))->total;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?Entry
	{
		return array_first($this->where(new RecordQuery()->where('id', '=', strtolower($id))->limit(1)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function named(string $type, string $key, ?string $language = null): ?Entry
	{
		$ids = $this->locations->idsWithKey($key);

		return $ids === [] ? null : array_first($this->where(
			new RecordQuery()
				->where('id', 'in', $ids)
				->where('type', '=', $type)
				->where('language', '=', $language ?? $this->app->languages->default->code)
				->limit(1)
		));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function translations(Entry $entry): array
	{
		$group = $entry->originalId ?? $entry->id;

		// Two lookups by id, which stores answer without a scan, not one
		// query of either.
		$grouped = $group === null ? [] : [
			...$this->where(new RecordQuery()->where('id', '=', $group)),
			...$this->where(new RecordQuery()->where('original_id', '=', $group))
		];
		$found   = [];

		foreach ($grouped as $translation) {
			$found[$translation->language] ??= $translation;
		}

		if (count($found) < 2) {
			return [$entry->language => $entry];
		}

		$entries = [];

		// In the languages' order, the default first.
		foreach (array_keys($this->app->languages->all()) as $code) {
			if (isset($found[$code])) {
				$entries[$code] = $found[$code];
			}
		}

		return $entries;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function translation(Entry $entry, string $language): ?Entry
	{
		return $entry->language === $language ? $entry : $this->translations($entry)[$language] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function term(string $taxonomy, string $slug, ?string $language = null): ?Entry
	{
		$type = $this->types->find($taxonomy);

		if ($type === null || (! $this->types->isTermType($type->name) && ! $this->types->hasTermPages($type->name))) {
			return null;
		}

		$language ??= $this->app->languages->default->code;
		$entry      = $this->named($taxonomy, $slug, $language);

		// Entries name a term by its original's slug (D-455), which finds
		// its translation (D-458), or the original without one (D-584).
		if ($entry === null && ! $this->app->languages->isDefault($language)) {
			$original = $this->named($taxonomy, $slug);
			$entry    = $original === null ? null : $this->translation($original, $language) ?? $original;
		}

		return $entry;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parentKey(string $type, string $key, ?string $language = null): ?string
	{
		$parent = $this->named($type, $key, $language)?->parentId;

		return $parent === null ? null : $this->locations->key($parent);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parent(Entry $entry): ?Entry
	{
		return $entry->parentId === null ? null : $this->find($entry->parentId);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function children(Entry $entry): array
	{
		if ($entry->landing || $entry->id === null) {
			return [];
		}

		$children = $this->where(new RecordQuery()->where('parent_id', '=', $entry->id));

		usort($children, Position::siblings(...));

		return $children;
	}

	/**
	 * Finds the entry's place among the listing's ids, so only the two
	 * neighbors are built.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function neighbors(Entry $entry, ?Query $query = null): array
	{
		$query ??= Query::fromArray($entry->type->listingArguments(), $this)
			->type($entry->type)
			->language($entry->language === '' ? null : $entry->language);

		$ids   = self::ids($this->select($query->limit(null)->offset(0))->records);
		$index = $entry->id === null ? false : array_search($entry->id, $ids, true);

		if ($index === false) {
			return ['before' => null, 'after' => null];
		}

		return [
			'before' => isset($ids[$index - 1]) ? $this->find($ids[$index - 1]) : null,
			'after'  => isset($ids[$index + 1]) ? $this->find($ids[$index + 1]) : null
		];
	}

	/**
	 * Counts the refs from the entries the query finds to the terms of
	 * each key's relations, an entry once a term. A relation's terms
	 * under `{type}.{name}` (`profile.authors`, `person.actors`) are that
	 * type's entries.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function termCounts(string|array $taxonomy, ?Query $query = null): array
	{
		$query  ??= $this->query();
		$compiled = $this->compile($query->limit(null)->offset(0));

		return $this->memo->remember('termCounts|' . implode(',', (array) $taxonomy) . '|' . $compiled->fingerprint(), fn (): array => $this->countTerms((array) $taxonomy, $compiled));
	}

	/**
	 * Counts, for `termCounts()`.
	 *
	 * @param  list<string> $taxonomies
	 * @return array<string, int>
	 */
	private function countTerms(array $taxonomies, RecordQuery $compiled): array
	{
		$relations = [];

		foreach ($taxonomies as $key) {
			foreach ($this->compiler->termRelations($key) as $relation) {
				$relations[$relation->name] = $relation;
			}
		}

		$listed = self::ids($this->records($compiled)->records);

		if ($relations === [] || $listed === []) {
			return [];
		}

		$table   = Ref::table(EntryTable::table()->area);
		$listed  = array_flip($listed);
		$sources = [];

		foreach ($this->stores->store($table)->select($table, new RecordQuery()->where('relation', 'in', array_keys($relations)))->records as $ref) {
			$source = EntryRecords::text($ref, 'source_id');

			if (isset($listed[$source])) {
				$sources[EntryRecords::text($ref, 'target_id')][$source] = true;
			}
		}

		$bySlug = [];
		$terms  = $sources === [] ? [] : $this->store()->select(EntryTable::table(), new RecordQuery()->where('id', 'in', array_map(strval(...), array_keys($sources)))->withoutContent())->records;

		foreach ($terms as $term) {
			$slug = EntryRecords::text($term, 'slug');

			if ($slug !== '') {
				$bySlug[$slug] = [...$bySlug[$slug] ?? [], ...$sources[$term->id] ?? []];
			}
		}

		ksort($bySlug, SORT_STRING);

		return array_map(count(...), $bySlug);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function editable(Entry|string $entry): EditableEntry
	{
		return $this->writer()->load($this->id($entry));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function editableAt(ContentType $type, string $key): ?EditableEntry
	{
		return $this->writer()->loadAt($type, $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function create(ContentType $type, string $slug, EntryChanges $changes, Entry|string|null $parent = null, ?DateTimeInterface $date = null): Entry
	{
		return $this->written($this->writer()->create($type, $slug, $changes, $parent === null ? null : $this->id($parent), $date));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes): Entry
	{
		$this->writeParents($type, $key);

		return $this->written($this->writer()->createAt($type, $key, $changes));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(Entry|string $entry, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): Entry
	{
		return $this->written($this->writer()->duplicate($this->id($entry), $slug, $changes, $date));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function change(Entry|string $entry, EntryChanges $changes, ?string $version = null): Entry
	{
		$id = $this->writer()->update($this->id($entry), $changes, $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(Entry|string $entry, string $slug, ?string $version = null): Entry
	{
		$id = $this->writer()->rename($this->id($entry), $slug, $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function changeLanguage(Entry|string $entry, string $language, ?string $version = null): Entry
	{
		$id = $this->writer()->changeLanguage($this->id($entry), $language, $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(Entry|string $entry, Entry|string|null $parent, ?string $version = null): Entry
	{
		$id = $this->writer()->move($this->id($entry), $parent === null ? null : $this->id($parent), $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(Entry|string $entry, ?string $version = null): Entry
	{
		$id = $this->writer()->trash($this->id($entry), $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(Entry|string $entry, ?string $version = null, ?string $status = Status::DRAFT): Entry
	{
		$id = $this->writer()->restore($this->id($entry), $version, $status);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Entry|string $entry, ?string $version = null): void
	{
		$this->writer()->delete($this->id($entry), $version);
		$this->memo->forget();
	}

	/**
	 * Writes the parent pages a tree's key names that don't exist yet
	 * (D-656), from the top, each a draft titled by its folder's name,
	 * so the page written at the key is under them.
	 */
	private function writeParents(ContentType $type, string $key): void
	{
		if (! $type->keysByFolder() || ! str_contains($key, '/')) {
			return;
		}

		$above = '';

		foreach (array_slice(explode('/', $key), 0, -1) as $segment) {
			$above = ltrim("{$above}/{$segment}", '/');

			if ($this->named($type->name, $above) === null) {
				$this->writer()->createAt($type, $above, new EntryChanges(set: ['title' => self::titleOf($segment), 'status' => Status::DRAFT]));
				$this->locations->refresh();
			}
		}
	}

	/**
	 * Returns a title made from a folder's name: `_cooks` is "Cooks".
	 */
	public static function titleOf(string $folder): string
	{
		return ucwords(trim(str_replace(['-', '_'], ' ', $folder)));
	}

	/**
	 * Returns an entry's id, or an id as given.
	 *
	 * @throws WriteException When the entry has none.
	 */
	private function id(Entry|string $entry): string
	{
		if (is_string($entry)) {
			return $entry;
		}

		return $entry->id ?? throw new WriteException((string) $this->messages->t('content.stored_entries.no_id', '"{title}" has no id, so it can\'t be changed here until it has one (Site Health, or `content:ids`; D-481).', title: $entry->title === '' ? $entry->key : $entry->title));
	}

	/**
	 * Returns the storage driver's writer.
	 */
	private function writer(): ContentWriter
	{
		return ($this->writer)();
	}

	/**
	 * Returns an entry just written, after dropping what entries led to
	 * before it (D-805).
	 *
	 * @throws WriteException When it can't be read back.
	 */
	private function written(string $id): Entry
	{
		$this->locations->refresh();
		$this->memo->forget();

		return $this->find($id) ?? throw new WriteException((string) $this->messages->t('content.stored_entries.not_read_back', 'The entry was written but couldn\'t be read back; check Site Health.'));
	}

	/**
	 * Returns the store that keeps entries.
	 */
	private function store(): RecordStore
	{
		return $this->stores->store(EntryTable::table());
	}

	/**
	 * Returns the entries a record query over `entries` finds, whatever
	 * their status, without their content.
	 *
	 * @return list<Entry>
	 */
	private function where(RecordQuery $query): array
	{
		return $this->entries($this->records($query->withoutContent())->records);
	}

	/**
	 * Builds entries from their records, with what each refers to (the
	 * `refs` rows, D-649) and the slugs of those targets, read in one go,
	 * for their terms. An entry is built once a request (D-806): a
	 * record already built, at the same version, comes back as the same
	 * object, however it was found.
	 *
	 * @param  list<Record> $records
	 * @return list<Entry>
	 */
	private function entries(array $records): array
	{
		$missing = array_values(array_filter($records, fn (Record $record): bool => ! $this->memo->has($this->key($record))));

		if ($missing !== []) {
			$this->build($missing);
		}

		return array_map(fn (Record $record): Entry => $this->memo->remember($this->key($record), fn (): Entry => $this->build([$record])[0]), $records);
	}

	/**
	 * Returns the memo's key for the entry built from a record: by its id,
	 * its version, and its status now, so a scheduled entry whose time
	 * comes is built again.
	 */
	private function key(Record $record): string
	{
		$status = $this->hydrator->status($record);

		return "entry|{$record->id}|" . ($record->version ?? '') . "|{$status->name}";
	}

	/**
	 * Builds entries from records not built yet, and keeps them.
	 *
	 * @param  list<Record> $records
	 * @return list<Entry>
	 */
	private function build(array $records): array
	{
		$table = EntryTable::table();
		$refs  = Refs::group($this->store(), $table, self::ids($records));
		$ids   = [];

		foreach ($refs as $relations) {
			foreach ($relations as $targets) {
				foreach ($targets as $id) {
					$ids[$id] = true;
				}
			}
		}

		$slugs = [];

		foreach ($ids === [] ? [] : $this->store()->select($table, new RecordQuery()->where('id', 'in', array_keys($ids))->withoutContent())->records as $target) {
			$slugs[$target->id] = EntryRecords::text($target, 'slug');
		}

		return array_map(
			fn (Record $record): Entry => $this->memo->remember($this->key($record), fn (): Entry => $this->hydrator->hydrate($record, $this->locations->key($record->id) ?? '', $this->locations->path($record->id), $refs[$record->id] ?? [], $slugs)),
			$records
		);
	}

	/**
	 * Returns records' ids.
	 *
	 * @param  list<Record> $records
	 * @return list<string>
	 */
	private static function ids(array $records): array
	{
		return array_map(static fn (Record $record): string => $record->id, $records);
	}

	/**
	 * Returns the current Unix time.
	 */
	private function now(): int
	{
		return $this->clock->now()->getTimestamp();
	}
}
