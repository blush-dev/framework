<?php

/**
 * Refs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Blush\Storage\StorageException;

/**
 * Writes and reads refs (D-649) between records of one area: what a
 * record refers to through each relation, in order. Queries filter by
 * them with `RecordQuery::whereRelated()` and load them with `with()`.
 *
 *     $refs->set($albums, $album->id, 'photos', [$first, $second]);
 *     $refs->of($albums, [$album->id], 'photos'); // [$album->id => ['photos' => [$first, $second]]]
 */
final readonly class Refs
{
	public function __construct(
		private RecordStores $stores
	) {}

	/**
	 * Replaces what a record refers to through a relation, in order, in
	 * one transaction. No targets removes them.
	 *
	 * @param  list<string> $targets
	 * @throws RecordException|StorageException
	 */
	public function set(Table $of, string $source, string $relation, array $targets): void
	{
		$table = Ref::table($of->area);
		$store = $this->stores->store($table);

		$store->transaction(function () use ($store, $table, $source, $relation, $targets): void {
			$old = $this->stores->query($table)->where('source_id', '=', $source)->where('relation', '=', $relation)->get();

			foreach ($old as $record) {
				$store->delete($table, $record->id);
			}

			foreach (array_values(array_unique($targets)) as $position => $target) {
				$store->save($table, new Ref($source, $relation, $target, $position)->record());
			}
		});
	}

	/**
	 * Returns what records refer to, by source id, then relation, in
	 * order: every relation, or the ones named.
	 *
	 * @param  list<string> $sources
	 * @return array<string, array<string, list<string>>>
	 * @throws RecordException|StorageException
	 */
	public function of(Table $of, array $sources, string ...$relations): array
	{
		return self::group($this->stores->store($of), $of, $sources, array_values($relations));
	}

	/**
	 * Reads the refs of some records from a store, grouped: for `of()`
	 * and a query's `with()`.
	 *
	 * @param  list<string> $sources
	 * @param  list<string> $relations Every relation when empty.
	 * @return array<string, array<string, list<string>>>
	 * @throws RecordException
	 */
	public static function group(RecordStore $store, Table $of, array $sources, array $relations = []): array
	{
		if ($sources === []) {
			return [];
		}

		$query = new RecordQuery()->where('source_id', 'in', array_values(array_unique($sources)));

		if ($relations !== []) {
			$query = $query->where('relation', 'in', $relations);
		}

		$grouped = [];

		foreach ($store->select(Ref::table($of->area), $query->orderBy('position'))->records as $record) {
			$ref = Ref::fromRecord($record);

			$grouped[$ref->source][$ref->relation][] = $ref->target;
		}

		return $grouped;
	}
}
