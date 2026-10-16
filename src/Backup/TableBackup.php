<?php

/**
 * Table backup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Blush\Storage\Record\Table;

/**
 * How a storage driver backs up the tables it keeps (D-882), in its own
 * form: files as files, a database as a database. Each `Storage` binds
 * one, and a backup asks the driver of each table's area, so a site whose
 * areas use different drivers gets each area's tables from its own.
 *
 * A backup copies its tables into a work folder a chunk at a time
 * (`copy()`), so a large site's backup can run as a job across several
 * requests, then `finish()` tidies what the driver copied before the
 * folder is zipped. A driver keeps what it writes under a folder named
 * for itself (`DRIVER`), as the archive has it.
 *
 * Restoring is the reverse (`restore()`, D-886): from a backup unzipped
 * into a work folder, each table's records replace the ones it has now,
 * a chunk at a time.
 */
interface TableBackup
{
	/**
	 * Returns the driver's name, the folder in a backup its tables are in.
	 */
	public function driver(): string;

	/**
	 * Returns about how many bytes a table holds as kept now, before
	 * compression: what a backup of it will hold.
	 */
	public function bytes(Table $table): int;

	/**
	 * Copies a table into the work folder from the cursor a last chunk
	 * stopped at (0 to start), and returns the cursor to go on from, or
	 * `null` once it's all copied.
	 *
	 * @throws BackupException When it can't be copied.
	 */
	public function copy(Table $table, string $work, int $cursor): ?int;

	/**
	 * Replaces a table's records with the backup's, from the work folder
	 * the backup was unzipped into, starting at the cursor a last chunk
	 * stopped at (0 to start), and returns the cursor to go on from, or
	 * `null` once it's all restored. A backup without the table's records
	 * leaves it empty.
	 *
	 * @throws BackupException When it can't be restored.
	 */
	public function restore(Table $table, string $work, int $cursor): ?int;

	/**
	 * Ends a backup, given every table of the driver's it copied.
	 *
	 * @param  list<Table> $tables
	 * @throws BackupException
	 */
	public function finish(array $tables, string $work): void;
}
