<?php

/**
 * Storage copy.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Auth\Accounts;
use Blush\Container\Container;
use Blush\Content\Index\IndexFreshness;
use Blush\Content\Index\Indexer;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Relation\Relations;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DefinitionTables;
use Blush\Content\Writer\FilesystemContentWriter;
use Blush\Content\Writer\RecordContentWriter;
use Blush\Core\Paths;
use Blush\Data\FileDataStore;
use Blush\Data\RecordDataStore;
use Blush\Job\FileJobStore;
use Blush\Media\MediaMetadataStore;
use Blush\Job\RecordJobStore;
use Blush\Session\RecordSessionStore;
use Blush\Settings\SettingGroups;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\SchemaStore;
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;

/**
 * Copies a site's stored data from one driver to another (D-644, D-662),
 * ids kept: from files into SQLite for now. Content is read from the
 * filesystem index, brought up to date first, each entry keeping its
 * front matter as written (`RecordContentWriter::asWritten()`) beside
 * its values, with the links between entries; then data, accounts, the
 * other registered tables (roles, plugins'), and jobs. Sessions aren't
 * copied: everyone signs in again. Files without ids aren't entries
 * (D-656), so they're counted, not copied.
 *
 * The copy is one transaction in the database, so a failed one leaves it
 * as it was. A database that already holds records is refused unless
 * they're to be replaced. The site keeps using the driver it names
 * until its config names the other.
 */
final readonly class StorageCopy
{
	/**
	 * The entries read from files at a time.
	 */
	private const int PAGE = 200;

	public function __construct(
		private Container $container,
		private StorageConfig $config,
		private StorageDriverFactory $drivers,
		private TableRegistry $tables,
		private ContentTypes $types,
		private Relations $relations,
		private ClockInterface $clock,
		private Paths $paths
	) {}

	/**
	 * Returns whether copying from one driver to another is supported.
	 */
	public static function supports(string $from, string $to): bool
	{
		return $from === StorageConfig::FILESYSTEM && $to === StorageConfig::SQLITE;
	}

	/**
	 * Copies everything, reporting each part as it's done. Returns how
	 * many of each were copied, by part, and how many files without ids
	 * were left.
	 *
	 * @param  Closure(string, int): void $progress Each part's name and count, when it's done.
	 * @return array{copied: array<string, int>, unidentified: int}
	 * @throws StorageException When the copy isn't supported, or the database already holds records and `$replace` is off.
	 */
	public function copy(string $from, string $to, bool $replace = false, ?Closure $progress = null): array
	{
		if (! self::supports($from, $to)) {
			throw new StorageException(sprintf('Copying from "%s" to "%s" isn\'t supported yet; only from "%s" to "%s".', $from, $to, StorageConfig::FILESYSTEM, StorageConfig::SQLITE));
		}

		$source  = $this->stores($from);
		$target  = $this->stores($to);
		$tables  = $this->tables->all();
		$store   = $target->store(EntryTable::table());
		$copied  = [];
		$done    = static function (string $part, int $count) use (&$copied, $progress): void {
			$copied[$part] = $count;

			if ($progress !== null) {
				$progress($part, $count);
			}
		};

		$filled = array_values(array_filter($tables, static fn (Table $table): bool => $table->area !== StorageArea::Sessions && $target->store($table)->count($table, new RecordQuery()) > 0));

		if ($filled !== [] && ! $replace) {
			throw new StorageException(sprintf('The database already holds records (in %s). Copy with --replace to replace them.', implode(', ', array_map(static fn (Table $table): string => $table->name, $filled))));
		}

		$this->container->make(Indexer::class)->index();

		$store->transaction(function () use ($tables, $filled, $source, $target, $done): void {
			foreach ($filled as $table) {
				foreach ($target->query($table)->get() as $record) {
					$target->store($table)->delete($table, $record->id);
				}
			}

			foreach ($tables as $table) {
				$destination = $target->store($table);

				if ($destination instanceof SchemaStore) {
					$destination->prepare($table);
				}
			}

			$done('entries', $this->entries($source, $target));
			$done('links', $this->table(Ref::table(StorageArea::Content), $source, $target));
			$done('data records', $this->data($target));
			$done('accounts', $this->table(Accounts::table(), $source, $target));

			$others = 0;

			foreach ($tables as $table) {
				if (! in_array($table->name, [EntryTable::TABLE, Ref::TABLE, RecordDataStore::TABLE, Accounts::TABLE, RecordSessionStore::TABLE, RecordJobStore::TABLE, RecordJobStore::STATE], true)) {
					$others += $this->table($table, $source, $target);
				}
			}

			$done('other records', $others);
			$done('jobs', $this->jobs($target));
		});

		if ($store instanceof SchemaStore) {
			$store->analyze();
		}

		return ['copied' => $copied, 'unidentified' => $this->unidentified()];
	}

	/**
	 * Copies the entries, each with its front matter as written.
	 */
	private function entries(RecordStores $source, RecordStores $target): int
	{
		$table  = EntryTable::table();
		$from   = $source->store($table);
		$to     = $target->store($table);
		$files  = $this->container->make(FilesystemContentWriter::class);
		$count  = 0;
		$offset = 0;

		do {
			$records = $from->select($table, new RecordQuery()->orderBy('id')->limit(self::PAGE)->offset($offset))->records;

			foreach ($records as $record) {
				$type    = $this->types->get(EntryRecords::text($record, 'type'));
				$written = RecordContentWriter::asWritten($files->load($record->id)->frontMatter, $type, $this->relations);

				$to->save($table, $record->withVersion(null)->withFields([...$record->fields, RecordContentWriter::WRITTEN => $written]));
				$count++;
			}

			$offset += self::PAGE;
		} while (count($records) === self::PAGE);

		return $count;
	}

	/**
	 * Copies a table's records as they are.
	 */
	private function table(Table $table, RecordStores $source, RecordStores $target): int
	{
		$to    = $target->store($table);
		$count = 0;

		foreach ($source->store($table)->select($table, new RecordQuery())->records as $record) {
			$to->save($table, $record->withVersion(null));
			$count++;
		}

		return $count;
	}

	/**
	 * Copies the data area's records kept by name, the tables apart.
	 */
	private function data(RecordStores $target): int
	{
		$from   = $this->container->make(FileDataStore::class);
		$to     = new RecordDataStore($target, $this->clock);
		$tables = [DefinitionTables::TYPES . '/', DefinitionTables::RELATIONS . '/', SettingGroups::TABLE . '/', MediaMetadataStore::FOLDER . '/'];
		$count  = 0;

		foreach (array_keys($from->records('')) as $name) {
			// Types, relations, settings, and media metadata are tables of
			// their own (D-672, D-673, D-675), copied
			// with the other tables.
			if (array_any($tables, static fn (string $folder): bool => str_starts_with($name, $folder))) {
				continue;
			}

			$to->save($name, $from->load($name) ?? []);
			$count++;
		}

		return $count;
	}

	/**
	 * Copies the jobs, whatever their status.
	 */
	private function jobs(RecordStores $target): int
	{
		$to   = new RecordJobStore($target, $this->clock, $this->paths);
		$jobs = $this->container->make(FileJobStore::class)->all();

		foreach ($jobs as $job) {
			$to->save($job);
		}

		return count($jobs);
	}

	/**
	 * Returns how many content files have no id, so weren't copied.
	 */
	private function unidentified(): int
	{
		$count = 0;

		foreach ($this->container->make(IndexFreshness::class)->fresh()->snapshot()->records as $record) {
			if ($record['id'] === null) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Returns record stores with every area on one driver.
	 */
	private function stores(string $driver): RecordStores
	{
		return new RecordStores(new StorageResolver(new StorageConfig($driver, sqlite: $this->config->sqlite), $this->drivers, $this->container));
	}
}
