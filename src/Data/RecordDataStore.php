<?php

/**
 * Record data store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Closure;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * Keeps the data area's records (D-642) in a `RecordStore`, for a driver
 * that keeps them as records (D-662: the `sqlite` driver): the `data`
 * table, one record a name (`types/post`, `settings`), with its folder
 * and when it last changed beside its values. Names have `/` in them, so
 * they're a declared, indexed field rather than the table's key (whose
 * values can't), kept unique here. A plain keyed table for
 * now, until the data area has repositories of its own (the data
 * layer's step 6).
 *
 * The saved settings are read before the container is built (D-642), so
 * the bootstrap builds one over a store it opened itself (`on()`).
 */
final readonly class RecordDataStore implements DataStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'data';

	public function __construct(
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock
	) {}

	/**
	 * Returns a data store over one record store.
	 */
	public static function on(RecordStore $store, ClockInterface $clock): self
	{
		return new self($store, $clock);
	}

	/**
	 * The data's table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, fields: ['name', 'folder']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function has(string $name): bool
	{
		return $this->find($name) !== null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(string $name): ?array
	{
		$record = $this->find($name);

		if ($record === null) {
			return null;
		}

		$data = $record->fields['data'] ?? [];

		return is_array($data) ? $data : [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function loadAll(string $folder): array
	{
		$folder = trim($folder, '/');

		if ($folder !== '') {
			DataLoader::checkName($folder);
		}

		$data = [];

		foreach ($this->select(new RecordQuery()->where('folder', Operator::Equal, $folder)) as $record) {
			$name  = $record->fields['name'] ?? null;
			$value = $record->fields['data'] ?? [];

			if (is_string($name)) {
				$data[basename($name)] = is_array($value) ? $value : [];
			}
		}

		ksort($data, SORT_STRING);

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function records(string $folder): array
	{
		$folder = trim($folder, '/');

		if ($folder !== '') {
			DataLoader::checkName($folder);
		}

		$prefix = $folder === '' ? '' : "{$folder}/";
		$query  = new RecordQuery();
		$found  = [];

		if ($prefix !== '') {
			$query = $query->where('name', Operator::Like, "{$prefix}%");
		}

		foreach ($this->select($query) as $record) {
			$name     = $record->fields['name'] ?? null;
			$modified = $record->fields['modified'] ?? 0;

			if (is_string($name) && str_starts_with($name, $prefix)) {
				$found[substr($name, strlen($prefix))] = is_int($modified) ? $modified : 0;
			}
		}

		ksort($found, SORT_STRING);

		return $found;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(string $name, array $data): void
	{
		unset($data[DataLoader::SCHEMA]);

		$table  = self::table();
		$record = $this->find($name) ?? Record::create($this->clock->now());
		$folder = dirname($name);

		try {
			$this->store()->save($table, $record->withFields([
				'name'     => $name,
				'folder'   => $folder === '.' ? '' : $folder,
				'modified' => $this->clock->now()->getTimestamp(),
				'data'     => $data
			]));
		} catch (RecordException | StorageException $error) {
			throw new DataStoreException(sprintf('Unable to write %s: %s', $this->location($name), $error->getMessage()), previous: $error);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $name): void
	{
		$record = $this->find($name);

		if ($record === null) {
			return;
		}

		try {
			$this->store()->delete(self::table(), $record->id);
		} catch (RecordException | StorageException $error) {
			throw new DataStoreException(sprintf('Unable to delete %s: %s', $this->location($name), $error->getMessage()), previous: $error);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		try {
			return $this->store()->transaction($write);
		} catch (RecordStoreFailure $error) {
			throw new DataStoreException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function location(string $name): string
	{
		DataLoader::checkName($name);

		return sprintf('%s in the database', $name);
	}

	/**
	 * Returns a record by name, or `null`.
	 *
	 * @throws InvalidData When the name is unsafe or the store can't be read.
	 */
	private function find(string $name): ?Record
	{
		DataLoader::checkName($name);

		try {
			return $this->store()->select(self::table(), new RecordQuery()->where('name', Operator::Equal, $name)->limit(1))->first();
		} catch (RecordException | StorageException $error) {
			throw new InvalidData(sprintf('%s can\'t be read: %s', $this->location($name), $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Returns the records a query finds.
	 *
	 * @return list<Record>
	 * @throws InvalidData When the store can't be read.
	 */
	private function select(RecordQuery $query): array
	{
		try {
			return $this->store()->select(self::table(), $query)->records;
		} catch (RecordException | StorageException $error) {
			throw new InvalidData(sprintf('The data can\'t be read: %s', $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Returns the store the table is kept in.
	 */
	private function store(): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store(self::table()) : $this->stores;
	}
}
