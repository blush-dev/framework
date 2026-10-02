<?php

/**
 * Indexed content repository.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Container\Attributes\Defer;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexFingerprint;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * The default repository: reads the content index and hydrates entries
 * from it.
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
final class IndexedRepository implements ContentRepository
{
	/**
	 * The name 1.x queries credit people by.
	 */
	private const string AUTHOR = 'author';

	private bool $checked = false;

	/**
	 * @param Closure(): Indexer $indexer
	 */
	public function __construct(
		private readonly ContentIndex $index,
		private readonly EntryHydrator $hydrator,
		private readonly ContentTypes $types,
		private readonly AppConfig $app,
		private readonly ContentConfig $config,
		private readonly ClockInterface $clock,
		private readonly IndexFingerprint $fingerprint,
		#[Defer(Indexer::class)] private readonly Closure $indexer
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
		$selection = $this->fresh()->select($this->resolved($query), $this->now());

		return new EntryCollection($selection->ids, $selection->total, $this->load(...));
	}

	/**
	 * Returns a query with 1.x's `author` reading the profiles type
	 * (D-351), unless a type is named `author`.
	 */
	private function resolved(Query $query): Query
	{
		$profiles = $this->types->profiles()?->name;

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
		return $this->fresh()->select($this->resolved($query)->limit(0), $this->now())->total;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?Entry
	{
		$record = $this->snapshot()->record($id);

		return $record === null ? null : $this->hydrator->hydrate($record);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function named(string $type, string $key, ?string $locale = null): ?Entry
	{
		$id = $this->snapshot()->find($locale ?? $this->app->locale, $type, $key);

		return $id === null ? null : $this->find($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function term(string $taxonomy, string $slug): ?Entry
	{
		$type = $this->types->find($taxonomy);

		if ($type === null || ! $type->hasTerms()) {
			return null;
		}

		$entry = $this->named($taxonomy, $slug);

		if ($entry !== null) {
			return $entry;
		}

		$snapshot = $this->snapshot();

		if ($snapshot->referencing($taxonomy, $slug) === []) {
			return null;
		}

		return $this->hydrator->virtual($type, $slug, $snapshot->labels[$taxonomy][$slug] ?? $slug, $snapshot->built, $this->app->locale);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parentKey(string $type, string $key, ?string $locale = null): ?string
	{
		$snapshot = $this->snapshot();
		$locale ??= $this->app->locale;
		$id       = $snapshot->find($locale, $type, $key);
		$parent   = $id === null ? null : $snapshot->records[$id]['parent'];

		return $parent !== null && $snapshot->find($locale, $type, $parent) !== null ? $parent : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parent(Entry $entry): ?Entry
	{
		$key = $entry->isVirtual() ? null : $entry->type->parentKey($entry->key, $entry->fields);

		return $key === null ? null : $this->named($entry->type->name, $key, $entry->locale);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function children(Entry $entry): array
	{
		if ($entry->isVirtual() || $entry->landing) {
			return [];
		}

		$children = array_values(array_filter(array_map(
			$this->find(...),
			$this->snapshot()->children($entry->locale, $entry->type->name, $entry->key)
		)));

		usort($children, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

		return $children;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function termCounts(string $taxonomy, ?Query $query = null): array
	{
		$snapshot = $this->snapshot();
		$listed   = array_flip($this->get(($query ?? $this->query())->limit(null)->offset(0))->ids);
		$counts   = [];

		foreach ($snapshot->terms[$taxonomy] ?? [] as $slug => $ids) {
			$counts[(string) $slug] = count(array_filter($ids, static fn (string $id): bool => isset($listed[$id])));
		}

		return $counts;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function redirects(): array
	{
		$snapshot  = $this->snapshot();
		$redirects = [];

		foreach ($snapshot->records as $id => $record) {
			$paths = $record['values']['redirect_from'] ?? null;

			if (! is_array($paths) || $paths === []) {
				continue;
			}

			$record = $snapshot->record((string) $id);
			$entry  = $record === null ? null : $this->hydrator->hydrate($record);

			foreach ($paths as $path) {
				if ($entry !== null && is_string($path) && ! isset($redirects[$path])) {
					$redirects[$path] = $entry;
				}
			}
		}

		return $redirects;
	}

	/**
	 * Returns the index, refreshed first if it's missing or stale or, in
	 * development, not yet checked in this request.
	 */
	private function fresh(): ContentIndex
	{
		if (! $this->checked) {
			$this->checked = true;

			$stale = ! $this->index->exists() || ! $this->fingerprint->matches($this->index->snapshot());

			if ($stale || ($this->config->autoIndex && $this->app->environment->isDevelopment())) {
				($this->indexer)()->index();
			}
		}

		return $this->index;
	}

	/**
	 * Returns the current snapshot.
	 */
	private function snapshot(): IndexSnapshot
	{
		return $this->fresh()->snapshot();
	}

	/**
	 * Hydrates entries by ID.
	 *
	 * @param  list<string> $ids
	 * @return list<Entry>
	 */
	private function load(array $ids): array
	{
		$snapshot = $this->snapshot();
		$entries  = [];

		foreach ($ids as $id) {
			$record = $snapshot->record($id);

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
