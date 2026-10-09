<?php

/**
 * Record job store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Core\Paths;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\Uuid;

/**
 * Keeps jobs as records inside a database driver (D-645, D-662): the
 * `jobs` table, each job a record with its own id, as
 * `JobRecord::toArray()` writes it, and named state in `job_state`,
 * keyed by name. A runner claims a job by saving it as running against
 * the version it read, in a transaction, so when two reach for one job,
 * only one save goes through.
 *
 * Named locks stay `flock()` on files in `storage/jobs`, as
 * `FileJobStore` takes them: they keep runners on one server apart, and
 * a SQLite site is one server.
 */
final readonly class RecordJobStore implements JobStore
{
	/**
	 * The jobs' table's name.
	 */
	public const string TABLE = 'jobs';

	/**
	 * The named state's table's name.
	 */
	public const string STATE = 'job_state';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock,
		private Paths $paths
	) {}

	/**
	 * The jobs' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Jobs, fields: ['status']);
	}

	/**
	 * The named state's table.
	 */
	public static function stateTable(): Table
	{
		return new Table(self::STATE, StorageArea::Jobs, key: 'name', fields: ['name']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(JobRecord $job): void
	{
		try {
			$this->stores->store(self::table())->save(self::table(), new Record(strtolower($job->id), self::fields($job)));
		} catch (RecordException | StorageException $e) {
			throw new JobException(sprintf('Unable to save job %s: %s', $job->id, $e->getMessage()), 0, $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?JobRecord
	{
		if (! Uuid::isValid($id)) {
			return null;
		}

		$record = $this->stores->store(self::table())->find(self::table(), strtolower($id));

		return $record === null ? null : self::job($record);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(?JobStatus $status = null): array
	{
		$query = new RecordQuery();

		if ($status !== null) {
			$query = $query->where('status', Operator::Equal, $status->value);
		}

		$jobs = [];

		foreach ($this->stores->store(self::table())->select(self::table(), $query)->records as $record) {
			$job = self::job($record);

			if ($job !== null) {
				$jobs[$record->id] = $job;
			}
		}

		// UUIDv7s sort by when they were made.
		ksort($jobs, SORT_STRING);

		return array_values($jobs);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function claim(JobRecord $queued, JobRecord $running): ?JobRecord
	{
		$table   = self::table();
		$store   = $this->stores->store($table);
		$running = $running->with(['status' => JobStatus::Running]);

		try {
			return $store->transaction(static function () use ($store, $table, $queued, $running): ?JobRecord {
				$stored = $store->find($table, strtolower($queued->id));

				if ($stored === null || ($stored->fields['status'] ?? null) !== JobStatus::Queued->value) {
					return null;
				}

				$store->save($table, $stored->withFields(self::fields($running)), $stored->version);

				return $running;
			});
		} catch (RecordException) {
			// Another runner saved it first, or holds the database.
			return null;
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id): void
	{
		if (Uuid::isValid($id)) {
			$this->stores->store(self::table())->delete(self::table(), strtolower($id));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function state(string $name): array
	{
		$state = $this->stores->store(self::stateTable())->findByKey(self::stateTable(), $name)?->fields['state'] ?? null;

		if (! is_array($state)) {
			return [];
		}

		/** @var array<string, mixed> */
		return array_filter($state, is_string(...), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function saveState(string $name, array $state): void
	{
		$table = self::stateTable();

		try {
			$store  = $this->stores->store($table);
			$record = $store->findByKey($table, $name) ?? Record::create($this->clock->now());

			$store->save($table, $record->withFields(['name' => $name, 'state' => $state]));
		} catch (RecordException | StorageException $e) {
			throw new JobException(sprintf('Unable to save the jobs\' "%s" state: %s', $name, $e->getMessage()), 0, $e);
		}
	}

	/**
	 * Returns a job's record fields: everything but its id, which is the
	 * record's.
	 *
	 * @return array<string, mixed>
	 */
	private static function fields(JobRecord $job): array
	{
		$fields = $job->toArray();

		unset($fields['id']);

		return $fields;
	}

	/**
	 * Returns a record's job, or `null` when it isn't one.
	 */
	private static function job(Record $record): ?JobRecord
	{
		return JobRecord::fromArray(['id' => $record->id, ...$record->fields]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function locked(string $name, callable $task): bool
	{
		if (! is_dir($this->paths->jobs)) {
			@mkdir($this->paths->jobs, 0775, true);
		}

		$handle = @fopen($this->paths->jobs . '/' . preg_replace('/[^a-z0-9-]/', '-', strtolower($name)) . '.lock', 'c');

		if ($handle === false) {
			return false;
		}

		try {
			if (! flock($handle, LOCK_EX | LOCK_NB)) {
				return false;
			}

			try {
				$task();
			} finally {
				flock($handle, LOCK_UN);
			}

			return true;
		} finally {
			fclose($handle);
		}
	}
}
