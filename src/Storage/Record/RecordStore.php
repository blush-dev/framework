<?php

/**
 * Record store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;

/**
 * What a storage driver implements to keep and fetch records (D-643):
 * `FileRecordStore` for files, `ArrayRecordStore` in memory, and a
 * database's later. Each answers every query alike, as the conformance
 * suite pins (`tests/Storage/Conformance`).
 *
 * Without an order, a query's records come in the order they were added
 * to their table. A store is reached through `RecordStores`, which
 * picks the one for a table's area.
 */
interface RecordStore
{
	/**
	 * Returns a record by id, or `null` when the table has none.
	 *
	 * @throws RecordException When the table can't be read.
	 */
	public function find(Table $table, string $id): ?Record;

	/**
	 * Returns a record by its key value (D-646), or `null` when the table
	 * has none.
	 *
	 * @throws RecordException When the table has no key or can't be read.
	 */
	public function findByKey(Table $table, string $key): ?Record;

	/**
	 * Returns the records with these ids, by id, in the order asked;
	 * ids the table hasn't are left out.
	 *
	 * @param  list<string> $ids
	 * @return array<string, Record>
	 * @throws RecordException When the table can't be read.
	 */
	public function findMany(Table $table, array $ids): array;

	/**
	 * Adds a record, or replaces the one with its id, keeping its place.
	 *
	 * @throws RecordException When its key is malformed or another record's, or it can't be written.
	 */
	public function save(Table $table, Record $record): void;

	/**
	 * Removes a record. A missing one is nothing to remove.
	 *
	 * @throws RecordException When it can't be removed.
	 */
	public function delete(Table $table, string $id): void;

	/**
	 * Returns the records a query matches, within its limit and offset,
	 * with how many it matched in all.
	 *
	 * @throws RecordException When the table can't be read.
	 */
	public function select(Table $table, RecordQuery $query): RecordResult;

	/**
	 * Returns how many records a query matches, whatever its limit and
	 * offset.
	 *
	 * @throws RecordException When the table can't be read.
	 */
	public function count(Table $table, RecordQuery $query): int;

	/**
	 * Returns how many matching records have each value of a key, in the
	 * order values sort in. A list counts once for each value it holds;
	 * `null`, and maps, aren't counted. The limit and offset don't apply.
	 *
	 * @return list<array{value: bool|int|float|string, count: int}>
	 * @throws RecordException When the table can't be read.
	 */
	public function countBy(Table $table, RecordQuery $query, string $key): array;

	/**
	 * Reduces the matching records' values of a key: the least or
	 * greatest (numbers and text, as they sort), or the sum or mean of
	 * the numbers. `null` when there are none, except a sum, `0`. The
	 * limit and offset don't apply.
	 *
	 * @throws RecordException When the table can't be read.
	 */
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null;

	/**
	 * Runs `$write` so no other transaction's writes interleave with it,
	 * and returns what it returns. When it throws, every record it saved
	 * or deleted is put back as it was, and the error goes on. One inside
	 * another puts back its own writes when it fails.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws RecordException When the store can't be locked.
	 */
	public function transaction(Closure $write): mixed;
}
