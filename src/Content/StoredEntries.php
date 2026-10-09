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
use Blush\Content\Entry\Position;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexFingerprint;
use Blush\Content\Index\IndexFreshness;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\ContentConfig;
use Blush\Content\Query\Selection;
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
use Blush\Storage\Record\RecordStores;

/**
 * The default `Entries` (D-654): queries compile to record queries the
 * content area's store answers (D-652, D-653), entries are hydrated from
 * the content index, and writes go through the storage driver's
 * `ContentWriter`, each answering the entry as it is after.
 *
 * The index is built on first use when none exists, or when it was built
 * with other content types, timezone, or locale (`IndexFingerprint`), so
 * a new or reconfigured site works without running `content:index`. In
 * development (with
 * `ContentConfig::$autoIndex`), the first use in each request also runs
 * an incremental index, which only reads files whose stat changed. In
 * production, reindexing is explicit: the CLI, the publish webhook, or
 * the admin.
 */
final class StoredEntries implements Entries
{
	/**
	 * The name 1.x queries credit people by.
	 */
	private const string AUTHOR = 'author';

	/**
	 * @param Closure(): ContentWriter $writer
	 */
	public function __construct(
		private readonly IndexFreshness $freshness,
		private readonly EntryHydrator $hydrator,
		private readonly ContentTypes $types,
		private readonly AppConfig $app,
		private readonly ClockInterface $clock,
		private readonly QueryCompiler $compiler,
		private readonly RecordStores $stores,
		#[Defer(ContentWriter::class)] private readonly Closure $writer
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
		$selection = $this->select($this->resolved($query));

		return new EntryCollection($selection->paths, $selection->total, $this->load(...));
	}

	/**
	 * Runs a query: compiled to a record query over `entries` (D-649),
	 * as of now, and answered by the store that keeps them (D-653), the
	 * entries found without their content, which hydration reads lazily.
	 * Entries are still hydrated from the index, by path, until the
	 * repository reads records (the data layer's step 3d).
	 *
	 * @throws InvalidQuery
	 */
	private function select(Query $query): Selection
	{
		$records = $this->fresh()->records();
		$table   = EntryTable::table();
		$found   = $this->stores->store($table)->select($table, $this->compiler->compile($query, $this->now(), $records)->withoutContent());

		return new Selection(array_values(array_filter(array_map(static fn (Record $record): ?string => $records->path($record->id), $found->records))), $found->total);
	}

	/**
	 * Returns a query with 1.x's `author` reading the profiles type
	 * (D-351), unless a type is named `author`, with no language meaning
	 * the default language (D-455), and in another language, the default
	 * language's entries standing in for missing translations when it
	 * asks for originals or the site's `untranslated` setting lists them
	 * (D-469).
	 */
	private function resolved(Query $query): Query
	{
		$profiles  = $this->types->profiles()?->name;
		$languages = $this->app->languages;

		if ($query->language === null) {
			$query = $query->language($languages->default->code);
		}

		$originals = $query->originals ?? $this->app->untranslated->lists();
		$query     = $query->fallback($originals && $languages->isOther($query->language ?? '') ? $languages->default->code : null);

		return $profiles === null || $profiles === self::AUTHOR || $this->types->has(self::AUTHOR)
			? $query
			: $query->withTaxonomyRenamed(self::AUTHOR, $profiles);
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
		return $this->select($this->resolved($query)->limit(0))->total;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?Entry
	{
		$path = $this->snapshot()->path($id);

		return $path === null ? null : $this->atPath($path);
	}

	/**
	 * Returns the entry the index keeps at a path.
	 */
	private function atPath(string $path): ?Entry
	{
		$record = $this->snapshot()->record($path);

		return $record === null ? null : $this->hydrator->hydrate($record);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function named(string $type, string $key, ?string $language = null): ?Entry
	{
		$path = $this->snapshot()->find($language ?? $this->app->languages->default->code, $type, $key);

		return $path === null ? null : $this->atPath($path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function translations(Entry $entry): array
	{
		$paths = $this->snapshot()->translations($entry->path);

		if ($paths === []) {
			return [$entry->language => $entry];
		}

		$entries = [];

		// In the languages' order, the default first.
		foreach (array_keys($this->app->languages->all()) as $code) {
			$translation = isset($paths[$code]) ? $this->atPath($paths[$code]) : null;

			if ($translation !== null) {
				$entries[$code] = $translation;
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
		if ($entry->language === $language) {
			return $entry;
		}

		$path = $this->snapshot()->translations($entry->path)[$language] ?? null;

		return $path === null ? null : $this->atPath($path);
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
		$snapshot = $this->snapshot();
		$language ??= $this->app->languages->default->code;
		$path     = $snapshot->find($language, $type, $key);
		$parent   = $path === null ? null : $snapshot->records[$path]['parent'];

		return $parent !== null && $snapshot->find($language, $type, $parent) !== null ? $parent : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parent(Entry $entry): ?Entry
	{
		// The index's parent, which a translation's is in its language (D-457).
		$key = $this->snapshot()->records[$entry->path]['parent'] ?? null;

		return $key === null ? null : $this->named($entry->type->name, $key, $entry->language);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function children(Entry $entry): array
	{
		if ($entry->landing) {
			return [];
		}

		$children = array_values(array_filter(array_map(
			$this->atPath(...),
			$this->snapshot()->children($entry->language, $entry->type->name, $entry->key)
		)));

		usort($children, Position::siblings(...));

		return $children;
	}

	/**
	 * Finds the entry's place among the listing's paths in the index, so
	 * only the two neighbors are built.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function neighbors(Entry $entry, ?Query $query = null): array
	{
		$query ??= Query::fromArray($entry->type->listingArguments(), $this)
			->type($entry->type)
			->language($entry->language === '' ? null : $entry->language);

		$paths = $this->select($this->resolved($query->limit(null)->offset(0)))->paths;
		$index = array_search($entry->path, $paths, true);

		if ($index === false) {
			return ['before' => null, 'after' => null];
		}

		return [
			'before' => isset($paths[$index - 1]) ? $this->atPath($paths[$index - 1]) : null,
			'after'  => isset($paths[$index + 1]) ? $this->atPath($paths[$index + 1]) : null
		];
	}

	/**
	 * A relation's terms under `{type}.{name}` (`profile.authors`,
	 * `person.actors`) are that type's entries.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function termCounts(string|array $taxonomy, ?Query $query = null): array
	{
		$snapshot = $this->snapshot();
		$listed   = array_flip($this->get(($query ?? $this->query())->limit(null)->offset(0))->paths);
		$found    = [];

		foreach (is_array($taxonomy) ? $taxonomy : [$taxonomy] as $key) {
			$type = strstr($key, '.', true) ?: $key;

			foreach ($snapshot->terms[$key] ?? [] as $slug => $paths) {
				if ($snapshot->has($type, (string) $slug)) {
					$found[(string) $slug] = [...$found[(string) $slug] ?? [], ...array_filter($paths, static fn (string $path): bool => isset($listed[$path]))];
				}
			}
		}

		return array_map(static fn (array $paths): int => count(array_unique($paths)), $found);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function redirects(): array
	{
		$snapshot  = $this->snapshot();
		$redirects = [];

		foreach ($snapshot->records as $path => $record) {
			$from = $record['values']['redirect_from'] ?? null;

			if (! is_array($from) || $from === []) {
				continue;
			}

			$record = $snapshot->record((string) $path);
			$entry  = $record === null ? null : $this->hydrator->hydrate($record);

			foreach ($from as $old) {
				if ($entry !== null && is_string($old) && ! isset($redirects[$old])) {
					$redirects[$old] = $entry;
				}
			}
		}

		return $redirects;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function editable(Entry|string $entry): EditableEntry
	{
		return $this->writer()->load(self::id($entry));
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
		return $this->written($this->writer()->create($type, $slug, $changes, $parent === null ? null : self::id($parent), $date));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes): Entry
	{
		return $this->written($this->writer()->createAt($type, $key, $changes));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(Entry|string $entry, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): Entry
	{
		return $this->written($this->writer()->duplicate(self::id($entry), $slug, $changes, $date));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function change(Entry|string $entry, EntryChanges $changes, ?string $version = null): Entry
	{
		$id = $this->writer()->update(self::id($entry), $changes, $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(Entry|string $entry, string $slug, ?string $version = null): Entry
	{
		$id = $this->writer()->rename(self::id($entry), $slug, $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(Entry|string $entry, Entry|string|null $parent, ?string $version = null): Entry
	{
		$id = $this->writer()->move(self::id($entry), $parent === null ? null : self::id($parent), $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(Entry|string $entry, ?string $version = null): Entry
	{
		$id = $this->writer()->trash(self::id($entry), $version);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(Entry|string $entry, ?string $version = null, ?Status $status = Status::Draft): Entry
	{
		$id = $this->writer()->restore(self::id($entry), $version, $status);

		return $this->written($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Entry|string $entry, ?string $version = null): void
	{
		$this->writer()->delete(self::id($entry), $version);
	}

	/**
	 * Returns an entry's id, or an id as given.
	 *
	 * @throws WriteException When the entry has none.
	 */
	private static function id(Entry|string $entry): string
	{
		if (is_string($entry)) {
			return $entry;
		}

		return $entry->id ?? throw new WriteException(sprintf('"%s" has no id, so it can\'t be changed here until it has one (Site Health, or `content:ids`; D-481).', $entry->title === '' ? $entry->key : $entry->title));
	}

	/**
	 * Returns the storage driver's writer.
	 */
	private function writer(): ContentWriter
	{
		return ($this->writer)();
	}

	/**
	 * Returns an entry just written.
	 *
	 * @throws WriteException When it can't be read back.
	 */
	private function written(string $id): Entry
	{
		return $this->find($id) ?? throw new WriteException('The entry was written but couldn\'t be read back; check Site Health.');
	}

	/**
	 * Returns the index, brought up to date once a request.
	 */
	private function fresh(): ContentIndex
	{
		return $this->freshness->fresh();
	}

	/**
	 * Returns the current snapshot.
	 */
	private function snapshot(): IndexSnapshot
	{
		return $this->fresh()->snapshot();
	}

	/**
	 * Hydrates entries by path.
	 *
	 * @param  list<string> $paths
	 * @return list<Entry>
	 */
	private function load(array $paths): array
	{
		$snapshot = $this->snapshot();
		$entries  = [];

		foreach ($paths as $path) {
			$record = $snapshot->record($path);

			if ($record !== null) {
				$entries[] = $this->hydrator->hydrate($record);
			}
		}

		return $entries;
	}

	/**
	 * Returns the current Unix time.
	 */
	private function now(): int
	{
		return $this->clock->now()->getTimestamp();
	}
}
