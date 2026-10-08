<?php

/**
 * Array record store.
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
use Throwable;

/**
 * Keeps records in memory, for tests (a plugin's included) and for
 * anything that needs a store nothing should outlive. It answers as
 * every store must (`tests/Storage/Conformance`).
 */
final class ArrayRecordStore implements RecordStore
{
	/**
	 * Records by table name, then by id, in the order they were added.
	 *
	 * @var array<string, array<string, Record>>
	 */
	private array $tables = [];

	/**
	 * The tables as each open transaction found them, outermost first.
	 *
	 * @var list<array<string, array<string, Record>>>
	 */
	private array $transactions = [];

	public function __construct(
		private readonly ArrayEvaluator $evaluator = new ArrayEvaluator()
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		return $this->tables[$table->name][strtolower($id)] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		if ($table->key === null) {
			throw new InvalidRecord(sprintf('"%s" has no key; find its records by id.', $table->name));
		}

		return array_find($this->tables[$table->name] ?? [], static fn (Record $record): bool => ($record->values[$table->key] ?? null) === $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		$found = [];

		foreach ($ids as $id) {
			$record = $this->find($table, $id);

			if ($record !== null) {
				$found[$record->id] = $record;
			}
		}

		return $found;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(Table $table, Record $record): void
	{
		$table->checkKey($record, $this->tables[$table->name] ?? []);

		$this->tables[$table->name][$record->id] = $record;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id): void
	{
		unset($this->tables[$table->name][strtolower($id)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		return $this->evaluator->select($this->records($table), $query);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		return $this->evaluator->count($this->records($table), $query);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		return $this->evaluator->countBy($this->records($table), $query, $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		return $this->evaluator->aggregate($this->records($table), $query, $function, $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		$this->transactions[] = $this->tables;

		try {
			$result = $write();
		} catch (Throwable $error) {
			$this->tables = (array) array_pop($this->transactions);

			throw $error;
		}

		array_pop($this->transactions);

		return $result;
	}

	/**
	 * A table's records, in the order they were added.
	 *
	 * @return list<Record>
	 */
	private function records(Table $table): array
	{
		return array_values($this->tables[$table->name] ?? []);
	}
}
