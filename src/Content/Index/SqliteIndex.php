<?php

/**
 * SQLite index.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Throwable;
use Blush\Content\ContentConfig;
use Blush\Content\Record\EntryTable;
use Blush\Core\Paths;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Table;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Support\Uuid;

/**
 * The filesystem driver's index rows in SQLite (D-659): the `entries`
 * and `refs` rows `PhpIndex` keeps, in `storage/index/content.sqlite`,
 * so queries are SQL. It's derived from the files, like the PHP index:
 * ignored by git and rebuilt by `content:index`.
 *
 * Each save of the index writes it whole into a new file, stamped with
 * the snapshot's stamp, and swaps it in, so a reader never sees half of
 * one. A reader uses it only when its stamp is the snapshot's (`store()`);
 * otherwise, or when PHP lacks SQLite with its JSON functions, or the
 * site turns it off (`ContentConfig::$sqliteIndex`), queries read the PHP
 * index as before. Bodies aren't kept: content comes from the files.
 */
final class SqliteIndex
{
	/**
	 * The index file's name in `storage/index`.
	 */
	public const string FILE = 'content.sqlite';

	/**
	 * The table its stamp is kept in.
	 */
	private const string STAMPS = 'index';

	/**
	 * Whether it's used, once asked.
	 */
	private ?bool $enabled = null;

	/**
	 * The store open on the file, and the stamp it was checked against.
	 *
	 * @var ?array{string, ?SqliteRecordStore}
	 */
	private ?array $open = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly ContentConfig $config
	) {}

	/**
	 * Returns the file's path.
	 */
	public function path(): string
	{
		return "{$this->paths->index}/" . self::FILE;
	}

	/**
	 * Returns whether the index is kept in SQLite: the site hasn't turned
	 * it off and PHP has SQLite with its JSON functions.
	 */
	public function enabled(): bool
	{
		return $this->enabled ??= $this->config->sqliteIndex && SqliteConnection::available();
	}

	/**
	 * Writes the rows, stamped, whole into a new file swapped in for the
	 * old one. When it can't be written, the old file goes, so queries
	 * read the PHP index; it never stops the PHP index being saved.
	 *
	 * @return bool Whether it was written.
	 */
	public function write(SnapshotRecords $records, string $stamp): bool
	{
		$this->open = null;

		if (! $this->enabled()) {
			$this->clear();

			return false;
		}

		$path = $this->path();
		$new  = "{$path}." . bin2hex(random_bytes(6)) . '.tmp';

		try {
			if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
				throw new IndexException(sprintf('The folder for the SQLite index, %s, couldn\'t be made.', dirname($path)));
			}

			$store = SqliteRecordStore::open($new, wal: false);
			$area  = EntryTable::table()->area;

			$store->replace(EntryTable::table(), self::records($records->entries));
			$store->replace(Ref::table($area), self::records($records->refs()));
			$store->replace(self::stamps(), [new Record(self::stampId(), ['stamp' => $stamp])]);
			$store->analyze();

			unset($store);

			if (! rename($new, $path)) {
				throw new IndexException(sprintf('The SQLite index couldn\'t be moved into place at %s.', $path));
			}
		} catch (Throwable) {
			if (is_file($new)) {
				unlink($new);
			}

			$this->clear();

			return false;
		}

		return true;
	}

	/**
	 * Returns the store on the file when its stamp is the one given, or
	 * `null` (no file, another stamp, or SQLite off), opened once for each
	 * stamp asked about.
	 */
	public function store(string $stamp): ?SqliteRecordStore
	{
		if ($this->open !== null && $this->open[0] === $stamp) {
			return $this->open[1];
		}

		$store = null;

		if ($stamp !== '' && $this->enabled() && is_file($this->path())) {
			try {
				$candidate = SqliteRecordStore::open($this->path(), wal: false);
				$found     = $candidate->find(self::stamps(), self::stampId());
				$store     = ($found?->fields['stamp'] ?? null) === $stamp ? $candidate : null;
			} catch (Throwable) {
				$store = null;
			}
		}

		$this->open = [$stamp, $store];

		return $store;
	}

	/**
	 * Removes the file.
	 */
	public function clear(): void
	{
		$this->open = null;

		if (is_file($this->path())) {
			unlink($this->path());
		}
	}

	/**
	 * Returns index rows as records, as they're stored, without content.
	 *
	 * @param  list<array{id: string, fields: array<string, mixed>, content?: ?string, version?: ?string}> $rows
	 * @return iterable<Record>
	 */
	private static function records(array $rows): iterable
	{
		foreach ($rows as $row) {
			yield ArrayEvaluator::record($row, false);
		}
	}

	/**
	 * Returns the table the stamp is kept in.
	 */
	private static function stamps(): Table
	{
		return new Table(self::STAMPS, EntryTable::table()->area);
	}

	/**
	 * Returns the stamp's record id.
	 */
	private static function stampId(): string
	{
		return Uuid::fromName('content/index/stamp');
	}
}
