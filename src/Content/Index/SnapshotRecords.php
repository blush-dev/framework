<?php

/**
 * Snapshot records.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Http\RelatedController;
use Blush\Content\Record\EntryTable;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Type\ContentTypes;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Ref;

/**
 * The index's entries as the record layer keeps them (D-649): one
 * `entries` row each, and a `refs` row for each link that isn't a
 * parent or a translation (those are the entries' `parent_id` and
 * `original_id`), built once from a snapshot. A file without an id
 * isn't an entry (D-656): it has no row, and Site Health and
 * `content:ids` give it one.
 *
 * Everything files imply by where they are becomes a value: the type,
 * language, slug (`''` for a landing page), parent (the entry at the
 * parent key, in the entry's type and language), original (the entry of
 * its translation group without a language suffix), and, for a page in
 * a relation archive's `_{word}/` folder in a type that doesn't nest,
 * its archive place (D-657).
 *
 * Records come in id order: for version 7 ids, the order entries were
 * made in, never their paths (D-516). They're built when the index is
 * (`build()`) and stored with it (`toArray()`, `IndexSnapshot::rows()`).
 *
 * @phpstan-import-type RowsArray from IndexSnapshot
 * @phpstan-import-type RowArray from IndexSnapshot
 * @phpstan-import-type RecordArray from IndexRecord
 */
final class SnapshotRecords
{
	/**
	 * Entry rows, in id order.
	 *
	 * @var list<RowArray>
	 */
	public private(set) array $entries = [];

	/**
	 * Ref rows.
	 *
	 * @var list<RowArray>
	 */
	private array $refRows = [];

	/**
	 * Refs looked up, by relation (and direction), once asked for.
	 *
	 * @var array<string, array<string, list<string>>>
	 */
	private array $lookups = [];

	/**
	 * Entry rows by id, once asked for.
	 *
	 * @var ?array<string, RowArray>
	 */
	private ?array $byId = null;

	/**
	 * Each table's row offsets by a key's value, once asked for, by
	 * `{table}:{key}`.
	 *
	 * @var array<string, array<string, list<int>>>
	 */
	private array $offsets = [];

	/**
	 * Paths by id.
	 *
	 * @var array<string, string>
	 */
	private array $paths = [];

	/**
	 * Ids by path.
	 *
	 * @var array<string, string>
	 */
	private array $ids = [];

	/**
	 * @param list<RowArray> $entries In id order.
	 * @param list<RowArray> $refs
	 */
	private function __construct(
		private readonly IndexSnapshot $snapshot,
		array $entries,
		array $refs
	) {
		$this->entries = $entries;
		$this->refRows = $refs;
		$this->ids     = self::ids($snapshot);
		$this->paths   = array_flip($this->ids);
	}

	/**
	 * Returns the records stored with a snapshot, or `null` when it has
	 * none (an index from before they were).
	 */
	public static function fromSnapshot(IndexSnapshot $snapshot): ?self
	{
		$rows = $snapshot->rows();

		if ($rows === null) {
			return null;
		}

		return new self($snapshot, $rows['entries'], $rows['refs']);
	}

	/**
	 * Builds a snapshot's records, with the relations its parents and
	 * translations are held in left out of refs (they're columns).
	 */
	public static function build(IndexSnapshot $snapshot, ContentTypes $types): self
	{
		$structural = [];
		$archives   = [];

		foreach ($types->relations() as $relation) {
			if ($relation->kind === RelationKind::Parent || $relation->kind === RelationKind::Translation) {
				$structural[] = $relation->name;
			}
		}

		// The folders of relation archives' pages in types that don't nest.
		foreach ($types->all() as $type) {
			if (! $type->keysByFolder()) {
				$archives[$type->name] = array_values(array_map(RelatedController::word(...), $types->relationArchives($type)));
			}
		}

		$ids       = self::ids($snapshot);
		$originals = [];

		foreach ($snapshot->records as $path => $record) {
			if ($record['original'] === null && isset($ids[$path])) {
				$originals[IndexRecord::groupOf($record)] = $ids[$path];
			}
		}

		$entries = [];

		foreach ($snapshot->records as $path => $record) {
			$id = $ids[$path] ?? null;

			if ($id === null) {
				continue;
			}

			$parent = $record['parent'] === null ? null : $snapshot->find($record['language'], $record['type'], $record['parent']);

			$place  = explode('/', $record['key']);

			$entries[$id] = ['id' => $id, 'version' => $record['hash'], 'fields' => self::fields(
				$record,
				$parent === null ? null : ($ids[$parent] ?? null),
				$record['original'] === null ? null : ($originals[IndexRecord::groupOf($record)] ?? null),
				count($place) === 2 && in_array($place[0], $archives[$record['type']] ?? [], true) ? $record['key'] : null
			)];
		}

		ksort($entries, SORT_STRING);

		$refs = [];

		foreach ($snapshot->graph()->all() as $link) {
			if (! in_array($link->relation, $structural, true)) {
				$refs[] = ArrayEvaluator::row(new Ref($link->source, $link->relation, $link->target, $link->position)->record());
			}
		}

		return new self($snapshot, array_values($entries), $refs);
	}

	/**
	 * Returns an index record's `entries` values, with its parent's and
	 * original's ids, and its place as a relation archive's page.
	 *
	 * @param  RecordArray          $record
	 * @return array<string, mixed>
	 */
	public static function fields(array $record, ?string $parentId, ?string $originalId, ?string $archive = null): array
	{
		return EntryTable::fields(
			$record['type'],
			$record['language'],
			$parentId,
			$record['landing'] ? '' : $record['slug'],
			$originalId,
			$record['status'],
			$record['visibility'],
			$record['published'],
			$record['updated'],
			$record['title'],
			$record['values'],
			$record['extra'],
			$archive
		);
	}

	/**
	 * The records as the snapshot stores them.
	 *
	 * @return RowsArray
	 */
	public function toArray(): array
	{
		return ['entries' => $this->entries, 'refs' => $this->refRows];
	}

	/**
	 * Each entry's id, by path, for the files that have one.
	 *
	 * @return array<string, string>
	 */
	private static function ids(IndexSnapshot $snapshot): array
	{
		$ids = [];

		foreach ($snapshot->records as $path => $record) {
			if ($record['id'] !== null) {
				$ids[$path] = $record['id'];
			}
		}

		return $ids;
	}

	/**
	 * Returns an entry's path, by id.
	 */
	public function path(string $id): ?string
	{
		return $this->paths[strtolower($id)] ?? null;
	}

	/**
	 * Returns the ref rows.
	 *
	 * @return list<RowArray>
	 */
	public function refs(): array
	{
		return $this->refRows;
	}

	/**
	 * Returns a relation's refs looked up, built once: sources by target,
	 * or, inverse, targets by source, lowercase.
	 *
	 * @return array<string, list<string>>
	 */
	public function refsBy(string $relation, bool $inverse): array
	{
		$key = ($inverse ? 'inverse:' : '') . $relation;

		if (isset($this->lookups[$key])) {
			return $this->lookups[$key];
		}

		$lookup = [];

		foreach ($this->refRows as $row) {
			$fields = $row['fields'];

			if (($fields['relation'] ?? null) !== $relation || ! is_string($fields['source_id'] ?? null) || ! is_string($fields['target_id'] ?? null)) {
				continue;
			}

			[$by, $found] = $inverse ? [$fields['source_id'], $fields['target_id']] : [$fields['target_id'], $fields['source_id']];

			$lookup[strtolower($by)][] = strtolower($found);
		}

		return $this->lookups[$key] = $lookup;
	}

	/**
	 * Returns a table's rows whose value at a key (`id`, or a field) is
	 * one of some values, in the table's order: a store's shortcut for a
	 * query asking for those, ahead of the conditions it then checks. The
	 * lookup is built once a request for each key asked by.
	 *
	 * @param  list<mixed>    $values
	 * @return list<RowArray>
	 */
	public function rowsWhere(string $table, string $key, array $values): array
	{
		$rows    = $table === EntryTable::TABLE ? $this->entries : $this->refRows;
		$lookup  = $this->offsets["{$table}:{$key}"] ??= self::offsetsBy($rows, $key);
		$offsets = [];

		foreach ($values as $value) {
			foreach (is_string($value) ? $lookup[$value] ?? [] : [] as $offset) {
				$offsets[$offset] = true;
			}
		}

		ksort($offsets);

		return array_map(static fn (int $offset): array => $rows[$offset], array_keys($offsets));
	}

	/**
	 * Returns rows' offsets by their text value at a key.
	 *
	 * @param  list<RowArray>             $rows
	 * @return array<string, list<int>>
	 */
	private static function offsetsBy(array $rows, string $key): array
	{
		$lookup = [];

		foreach ($rows as $offset => $row) {
			$value = $key === 'id' ? $row['id'] : $row['fields'][$key] ?? null;

			if (is_string($value)) {
				$lookup[$value][] = $offset;
			}
		}

		return $lookup;
	}

	/**
	 * Returns an entry's row, by id.
	 *
	 * @return ?RowArray
	 */
	public function entry(string $id): ?array
	{
		$this->byId ??= array_column($this->entries, null, 'id');

		return $this->byId[strtolower($id)] ?? null;
	}
}
