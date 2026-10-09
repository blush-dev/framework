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

use Override;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\EntryValues;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Type\ContentTypes;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Ref;
use Blush\Support\Uuid;

/**
 * The index's entries as the record layer keeps them (D-649): one
 * `entries` row each, and a `refs` row for each link that isn't a
 * parent or a translation (those are the entries' `parent_id` and
 * `original_id`), built once from a snapshot. An entry without an id,
 * which links nothing in the graph, has refs from the terms its front
 * matter writes, each to the entry of the relation's types with that
 * key, as 1.x files do (D-078).
 *
 * Everything files imply by where they are becomes a value: the type,
 * language, slug (`''` for a landing page), parent (the entry at the
 * parent key, in the entry's type and language), and original (the
 * entry of its translation group without a language suffix). An entry
 * without an id has a steady one from its path (`Uuid::fromName()`).
 *
 * Records come in id order: for version 7 ids, the order entries were
 * made in, never their paths (D-516). They're built when the index is
 * (`build()`) and stored with it (`toArray()`, `IndexSnapshot::rows()`).
 *
 * @phpstan-import-type RowsArray from IndexSnapshot
 * @phpstan-import-type RowArray from IndexSnapshot
 */
final class SnapshotRecords implements EntryLocations
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
	 * translations are held in left out of refs (they're columns), and
	 * the relations each term key names, for the refs of entries without
	 * ids.
	 */
	public static function build(IndexSnapshot $snapshot, ContentTypes $types): self
	{
		$structural = [];
		$terms      = [];

		foreach ($types->relations() as $relation) {
			if ($relation->kind === RelationKind::Parent || $relation->kind === RelationKind::Translation) {
				$structural[] = $relation->name;
			}

			$key = $relation->termKey();

			if ($key !== null) {
				$terms[$key][$relation->name] = $relation->to;
			}

			// A credit relation files under its profiles type too (D-602).
			if ($relation->kind === RelationKind::Credit && count($relation->to) === 1) {
				$terms[$relation->to[0]][$relation->name] = $relation->to;
			}
		}

		$ids       = self::ids($snapshot);
		$originals = [];

		foreach ($snapshot->records as $path => $record) {
			if ($record['original'] === null) {
				$originals[IndexRecord::groupOf($record)] = $ids[$path];
			}
		}

		$entries = [];

		foreach ($snapshot->records as $path => $record) {
			$id     = $ids[$path];
			$parent = $record['parent'] === null ? null : $snapshot->find($record['language'], $record['type'], $record['parent']);
			$front  = [...$record['extra'], ...$record['values']];

			$entries[$id] = ['id' => $id, 'version' => $record['hash'], 'fields' => [
				'type'        => $record['type'],
				'language'    => $record['language'],
				'parent_id'   => $parent === null ? null : ($ids[$parent] ?? null),
				'slug'        => $record['landing'] ? '' : $record['slug'],
				'original_id' => $record['original'] === null ? null : ($originals[IndexRecord::groupOf($record)] ?? null),
				'status'      => $record['status'],
				'visibility'  => $record['visibility'],
				'published'   => $record['published'] === null ? null : EntryTable::time($record['published']),
				'updated'     => EntryTable::time($record['updated']),
				'title'       => $record['title'],
				'position'    => is_int($record['values']['position'] ?? null) ? $record['values']['position'] : null,
				'fields'      => $front,
				'slugs'       => EntryValues::slugs($front)
			]];
		}

		ksort($entries, SORT_STRING);

		$refs = [];

		foreach ($snapshot->graph()->all() as $link) {
			if (! in_array($link->relation, $structural, true)) {
				$refs[] = ArrayEvaluator::row(new Ref($link->source, $link->relation, $link->target, $link->position)->record());
			}
		}

		$built = new self($snapshot, array_values($entries), $refs);

		return new self($snapshot, $built->entries, [...$refs, ...$built->termRefs($terms, $originals)]);
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
	 * Each entry's id, by path: its own, or a steady one from its path.
	 *
	 * @return array<string, string>
	 */
	private static function ids(IndexSnapshot $snapshot): array
	{
		$ids = [];

		foreach ($snapshot->records as $path => $record) {
			$ids[$path] = $record['id'] ?? Uuid::fromName('content/' . $path);
		}

		return $ids;
	}

	/**
	 * Refs for entries without an id, from the terms their front matter
	 * writes: to the entry of the relation's types with the written key,
	 * in the entry's language first, then any, and to its original when
	 * it's a translation. Written values that name no entry make none.
	 *
	 * @param  array<string, array<string, list<string>>> $terms
	 * @param  array<string, string>                      $originals
	 * @return list<RowArray>
	 */
	private function termRefs(array $terms, array $originals): array
	{
		$refs = [];

		foreach ($this->snapshot->records as $path => $record) {
			if ($record['id'] !== null) {
				continue;
			}

			foreach ($record['terms'] as $key => $written) {
				foreach ($terms[$key] ?? [] as $relation => $types) {
					foreach ($written as $position => $value) {
						$target = $this->target($types, (string) $value, $record['language'], $originals);

						if ($target !== null) {
							$ref = new Ref($this->ids[$path], $relation, $target, $position)->record();

							$refs[$ref->id] = ArrayEvaluator::row($ref);
						}
					}
				}
			}
		}

		return array_values($refs);
	}

	/**
	 * The id of the original entry a written key names in some types.
	 *
	 * @param list<string>          $types
	 * @param array<string, string> $originals
	 */
	private function target(array $types, string $key, string $language, array $originals): ?string
	{
		foreach ($types as $type) {
			$path = $this->snapshot->find($language, $type, $key);

			foreach ($path === null ? array_keys($this->snapshot->keys) : [] as $other) {
				$path ??= $this->snapshot->find((string) $other, $type, $key);
			}

			if ($path !== null) {
				$record = $this->snapshot->records[$path];

				return $record['original'] === null ? $this->ids[$path] : ($originals[IndexRecord::groupOf($record)] ?? $this->ids[$path]);
			}
		}

		return null;
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
	 * Returns an entry's row, by id.
	 *
	 * @return ?RowArray
	 */
	public function entry(string $id): ?array
	{
		$this->byId ??= array_column($this->entries, null, 'id');

		return $this->byId[strtolower($id)] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsIn(array $folders): array
	{
		$ids = [];

		foreach ($this->snapshot->records as $path => $record) {
			if (in_array($record['directory'], $folders, true)) {
				$ids[] = $this->ids[$path];
			}
		}

		return $ids;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function idsWithKey(string $key): array
	{
		$ids = [];

		foreach ($this->snapshot->records as $path => $record) {
			if ($record['key'] === $key) {
				$ids[] = $this->ids[$path];
			}
		}

		return $ids;
	}
}
