<?php

/**
 * SQLite entry store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Conformance;

use LogicException;
use Override;
use Psr\Clock\ClockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Blush\Content\Entries;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\QueryCompiler;
use Blush\Content\Record\RecordLocations;
use Blush\Content\StoredEntries;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Field\FieldContext;
use Blush\Markdown\MarkdownParser;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\SqliteStorage;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageDriverFactory;
use Blush\Storage\StorageDriverRegistry;
use Blush\Storage\StorageResolver;

/**
 * The SQLite driver (D-662): the site's records copied, in the order
 * they were made in, into the site's database, `user/site.sqlite`, which
 * the `sqlite` driver gives for the content area, with `Entries` built
 * over it and locations read from records. Reads only, until the driver
 * writes content (step 5b).
 */
#[CoversClass(SqliteStorage::class)]
#[CoversClass(SqliteRecordStore::class)]
#[CoversClass(StoredEntries::class)]
#[CoversClass(EntryHydrator::class)]
#[CoversClass(QueryCompiler::class)]
#[CoversClass(RecordLocations::class)]
final class SqliteEntryStoreTest extends EntryStoreConformance
{
	#[Override]
	protected function setUp(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		parent::setUp();
	}

	#[Override]
	protected function stores(Application $app): RecordStores
	{
		$container = $app->container();
		$files     = $container->make(RecordStores::class);
		$config    = new StorageConfig(areas: [StorageArea::Content->value => StorageConfig::SQLITE]);
		$stores    = new RecordStores(new StorageResolver($config, new StorageDriverFactory($container->make(StorageDriverRegistry::class), $container), $container));

		$this->assertFalse(is_file($this->temporaryDirectory() . '/' . StorageConfig::SQLITE_FILE), 'Made on first use.');

		foreach ([EntryTable::table(), Ref::table(StorageArea::Content)] as $table) {
			$sqlite = $stores->store($table);

			foreach ($files->store($table)->select($table, new RecordQuery())->records as $record) {
				$sqlite->save($table, $record->withVersion(null));
			}
		}

		$this->assertInstanceOf(SqliteRecordStore::class, $stores->store(EntryTable::table()));
		$this->assertFileExists($this->temporaryDirectory() . '/' . StorageConfig::SQLITE_FILE);

		return $stores;
	}

	#[Override]
	protected function entries(Application $app, RecordStores $stores): Entries
	{
		return self::recordEntries($app, $stores);
	}
}
