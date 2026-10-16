<?php

/**
 * SQLite table backup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Override;
use Throwable;
use Blush\Job\JobStatus;
use Blush\Job\RecordJobStore;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\Table;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Storage\StorageConfig;

/**
 * The SQLite driver's backups (D-882): the first table copied takes a
 * snapshot of the whole database (`VACUUM INTO`), so every table in it is
 * from one moment, and finishing drops the tables the backup doesn't hold
 * (sessions and secrets among them) and the jobs that hadn't finished,
 * leaving `sqlite/site.sqlite` in the archive. Restoring replaces each
 * table's rows with the copy's, in one transaction a table, with the copy
 * attached (`SqliteRecordStore::replaceFrom()`). No `exec()` is needed.
 */
final readonly class SqliteTableBackup implements TableBackup
{
	public const string DRIVER = StorageConfig::SQLITE;

	/**
	 * The copy's path in the work folder, and in the archive.
	 */
	public const string FILE = self::DRIVER . '/site.sqlite';

	public function __construct(private SqliteRecordStore $store)
	{}

	#[Override]
	public function driver(): string
	{
		return self::DRIVER;
	}

	#[Override]
	public function bytes(Table $table): int
	{
		try {
			return $this->store->bytes($table);
		} catch (RecordStoreFailure) {
			return 0;
		}
	}

	#[Override]
	public function copy(Table $table, string $work, int $cursor): ?int
	{
		$file = "{$work}/" . self::FILE;

		if (is_file($file)) {
			return null;
		}

		if (! is_dir(dirname($file)) && ! @mkdir(dirname($file), 0o770, true) && ! is_dir(dirname($file))) {
			throw new BackupException(sprintf('The folder %s couldn\'t be made.', dirname($file)));
		}

		try {
			$this->store->copyInto($file);
		} catch (RecordStoreFailure $e) {
			throw new BackupException(sprintf('The database couldn\'t be copied: %s', $e->getMessage()), 0, $e);
		}

		return null;
	}

	#[Override]
	public function restore(Table $table, string $work, int $cursor): ?int
	{
		$file = "{$work}/" . self::FILE;

		if (! is_file($file)) {
			throw new BackupException(sprintf('The backup has no database to restore %s from.', $table->name));
		}

		try {
			// Only finished jobs are replaced; queued and running ones (this
			// restore's own among them) are left as they are.
			$table->name === RecordJobStore::TABLE
				? $this->store->replaceFrom($file, $table, 'status', [JobStatus::Done->value, JobStatus::Failed->value])
				: $this->store->replaceFrom($file, $table);
		} catch (RecordStoreFailure $e) {
			throw new BackupException(sprintf('%s couldn\'t be restored: %s', $table->name, $e->getMessage()), 0, $e);
		}

		return null;
	}

	#[Override]
	public function finish(array $tables, string $work): void
	{
		$file = "{$work}/" . self::FILE;

		if (! is_file($file)) {
			return;
		}

		try {
			$copy = SqliteRecordStore::open($file);

			// Only finished jobs are kept; a queued or running one (this
			// backup's own among them) would run again on a restore.
			foreach ($tables as $table) {
				if ($table->name === RecordJobStore::TABLE) {
					$query = new RecordQuery()->where('status', 'in', [JobStatus::Queued->value, JobStatus::Running->value])->withoutContent();

					foreach ($copy->select($table, $query)->records as $record) {
						$copy->delete($table, $record->id);
					}
				}
			}

			$copy->keepOnly($tables);
		} catch (Throwable $e) {
			throw new BackupException(sprintf('The database\'s copy couldn\'t be trimmed to the backup\'s parts: %s', $e->getMessage()), 0, $e);
		}
	}
}
