<?php

/**
 * Memoized record store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;
use Override;
use Blush\Support\RequestMemo;

/**
 * A driver's store, with every read kept in the request memo (D-807):
 * a record found by id or key, records found together, and a query's
 * answer are asked of the driver once a request, and the same record
 * comes back. `RecordStores` hands out every store this way, so every
 * table, on every driver, is read once however often it's asked.
 *
 * - **Writes drop the memo:** a save or delete, and a transaction when
 *   it ends (whether it kept its writes or put them back).
 * - **Reads inside a transaction** go to the driver, since a
 *   transaction reads what's stored under its lock (a job's claim, a
 *   conflict check).
 */
final class MemoizedRecordStore implements RecordStore, LocatingStore
{
	/**
	 * How many transactions are open.
	 */
	private int $transactions = 0;

	public function __construct(
		public readonly RecordStore $store,
		private readonly RequestMemo $memo
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		return $this->read($table, "find|{$id}", fn (): ?Record => $this->store->find($table, $id));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		return $this->read($table, "key|{$key}", fn (): ?Record => $this->store->findByKey($table, $key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		return $this->read($table, 'many|' . implode(',', $ids), fn (): array => $this->store->findMany($table, $ids));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(Table $table, Record $record, ?string $version = null): Record
	{
		try {
			return $this->store->save($table, $record, $version);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id, ?string $version = null): void
	{
		try {
			$this->store->delete($table, $id, $version);
		} finally {
			$this->memo->forget();
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		return $this->read($table, 'select|' . $query->fingerprint(), fn (): RecordResult => $this->store->select($table, $query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		return $this->read($table, 'count|' . $query->fingerprint(), fn (): int => $this->store->count($table, $query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		return $this->read($table, "countBy|{$key}|" . $query->fingerprint(), fn (): array => $this->store->countBy($table, $query, $key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		return $this->read($table, "aggregate|{$function->value}|{$key}|" . $query->fingerprint(), fn (): int|float|string|bool|null => $this->store->aggregate($table, $query, $function, $key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		$this->transactions++;

		try {
			return $this->store->transaction($write);
		} finally {
			$this->transactions--;
			$this->memo->forget();
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function location(Table $table, string $key): string
	{
		return $this->store instanceof LocatingStore ? $this->store->location($table, $key) : "{$table->name}/{$key}";
	}

	/**
	 * Returns what a read gives, kept in the memo outside transactions.
	 *
	 * @template T
	 * @param  Closure(): T $read
	 * @return T
	 */
	private function read(Table $table, string $what, Closure $read): mixed
	{
		return $this->transactions > 0
			? $read()
			: $this->memo->remember("records|{$table->area->value}|{$table->name}|{$what}", $read);
	}
}
