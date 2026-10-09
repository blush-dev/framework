<?php

/**
 * Keyed table.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Storage\StorageException;

/**
 * A keyed table's records as plain data, by key (D-672): for tables
 * whose records are definitions read whole and written whole, such as
 * the site's content types and relations. A record's fields are its data,
 * without its key; saving one keeps its id, or gives a new record one.
 * `id` and `content` are a record's own (`Record::RESERVED`), so data
 * holding either is refused.
 *
 *     $types = new KeyedTable($stores, new Table('types', StorageArea::Data, key: 'name'), $clock);
 *     $types->save('movie', ['folder' => 'library/movies']);
 *     $types->find('movie'); // ['folder' => 'library/movies']
 *
 * Every failure is a `RecordException`, the driver's included.
 */
final readonly class KeyedTable
{
	/**
	 * @throws InvalidRecord When the table has no key.
	 */
	public function __construct(
		private RecordStores|RecordStore $stores,
		public Table $table,
		private ClockInterface $clock
	) {
		if ($table->key === null) {
			throw new InvalidRecord(sprintf('"%s" has no key, so its records can\'t be kept by one.', $table->name));
		}
	}

	/**
	 * Every record's data, by key, sorted by key.
	 *
	 * @return array<string, array<string, mixed>>
	 * @throws RecordException When the table can't be read.
	 */
	public function all(): array
	{
		$all = [];

		foreach ($this->store()->select($this->table, new RecordQuery())->records as $record) {
			$all[$this->keyOf($record)] = $this->data($record);
		}

		ksort($all, SORT_STRING);

		return $all;
	}

	/**
	 * A record's data, or `null` when there's none by that key.
	 *
	 * @return ?array<string, mixed>
	 * @throws RecordException When the table can't be read.
	 */
	public function find(string $key): ?array
	{
		$record = $this->record($key);

		return $record === null ? null : $this->data($record);
	}

	/**
	 * Whether there's a record by a key.
	 *
	 * @throws RecordException When the table can't be read.
	 */
	public function has(string $key): bool
	{
		return $this->record($key) !== null;
	}

	/**
	 * Writes a record's data whole, keeping its id, or making a record
	 * with a new one.
	 *
	 * @param  array<array-key, mixed> $data A map of fields.
	 * @throws RecordException When the key or data won't do, or it can't be written.
	 */
	public function save(string $key, array $data): void
	{
		if ($data !== [] && array_is_list($data)) {
			throw new InvalidRecord(sprintf('A record in "%s" is a map of fields, not a list.', $this->table->name));
		}

		$reserved = array_intersect(array_map(strval(...), array_keys($data)), Record::RESERVED);

		if ($reserved !== []) {
			throw new InvalidRecord(sprintf('"%s" is a record\'s own, so it can\'t be a field of one in "%s".', implode('", "', $reserved), $this->table->name));
		}

		$column = (string) $this->table->key;
		$record = $this->record($key) ?? Record::create($this->clock->now());
		$data   = array_diff_key(array_combine(array_map(strval(...), array_keys($data)), array_values($data)), [$column => true]);

		$this->store()->save($this->table, new Record($record->id, [...$data, $column => $key], $record->content));
	}

	/**
	 * Removes a record. A missing one is nothing to remove.
	 *
	 * @throws RecordException When it can't be removed.
	 */
	public function delete(string $key): void
	{
		$record = $this->record($key);

		if ($record !== null) {
			$this->store()->delete($this->table, $record->id);
		}
	}

	/**
	 * Runs `$write` in a transaction of the table's store, putting back
	 * what it wrote when it throws (`RecordStore::transaction()`).
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws RecordException When the store can't be locked.
	 */
	public function transaction(Closure $write): mixed
	{
		return $this->store()->transaction($write);
	}

	/**
	 * Where a record is kept, for people: as the store says, else the
	 * table and key (`types/movie`).
	 */
	public function location(string $key): string
	{
		try {
			$store = $this->store();
		} catch (RecordException) {
			$store = null;
		}

		return $store instanceof LocatingStore ? $store->location($this->table, $key) : "{$this->table->name}/{$key}";
	}

	/**
	 * Returns a record by its key, or `null`.
	 *
	 * @throws RecordException
	 */
	private function record(string $key): ?Record
	{
		return Table::isKeyValue($key) ? $this->store()->findByKey($this->table, $key) : null;
	}

	/**
	 * Returns a record's key value.
	 *
	 * @throws InvalidRecord When it has none.
	 */
	private function keyOf(Record $record): string
	{
		return (string) $this->table->keyOf($record);
	}

	/**
	 * Returns a record's data: its fields without its key.
	 *
	 * @return array<string, mixed>
	 */
	private function data(Record $record): array
	{
		return array_diff_key($record->fields, [(string) $this->table->key => true]);
	}

	/**
	 * The store that keeps the table.
	 *
	 * @throws RecordStoreFailure When its area's driver keeps no records.
	 */
	private function store(): RecordStore
	{
		if ($this->stores instanceof RecordStore) {
			return $this->stores;
		}

		try {
			return $this->stores->store($this->table);
		} catch (StorageException $error) {
			throw new RecordStoreFailure($error->getMessage(), previous: $error);
		}
	}
}
