<?php

/**
 * Storage tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Console\CommandRegistry;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\RecordLocations;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\FilesystemContentWriter;
use Blush\Job\FileJobStore;
use Blush\Job\JobStore;
use Blush\Session\FileSessionStore;
use Blush\Session\SessionStore;
use Blush\Storage\FilesystemStorage;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\SqliteStorage;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Storage\Storage;
use Blush\Storage\StorageArea;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageDriver;
use Blush\Storage\StorageDriverFactory;
use Blush\Storage\StorageDriverRegistrar;
use Blush\Storage\StorageDriverRegistry;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;
use Blush\Tests\BootsScratchSite;

#[CoversClass(FilesystemStorage::class)]
#[CoversClass(SqliteStorage::class)]
#[CoversClass(StorageDriver::class)]
#[CoversClass(StorageDriverFactory::class)]
#[CoversClass(StorageDriverRegistrar::class)]
#[CoversClass(StorageDriverRegistry::class)]
#[CoversClass(StorageResolver::class)]
final class StorageTest extends TestCase
{
	use BootsScratchSite;

	public function testFilesystemIsTheDefaultForEveryArea(): void
	{
		$container = $this->scratchApplication()->container();

		$this->assertSame('filesystem', $container->make(StorageConfig::class)->driver);
		$this->assertInstanceOf(FilesystemSource::class, $container->make(ContentSource::class));
		$this->assertInstanceOf(FilesystemContentWriter::class, $container->make(ContentWriter::class));
		$this->assertInstanceOf(Accounts::class, $container->make(Accounts::class));
		$this->assertInstanceOf(FileSessionStore::class, $container->make(SessionStore::class));
		$this->assertInstanceOf(FileJobStore::class, $container->make(JobStore::class));
	}

	public function testAConfigFileWinsOverTheEnvironment(): void
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'filesystem']);\n");

		$container = $this->scratchApplication(['STORAGE_DRIVER' => 'nowhere'])->container();

		$this->assertInstanceOf(FilesystemSource::class, $container->make(ContentSource::class));
	}

	public function testAnAreaUsesItsOwnDriver(): void
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['sessions' => 'memory']);\n");

		$container = $this->scratchApplication()->container();
		$container->make(StorageDriverRegistry::class)->register('memory', SessionsOnly::class);

		$this->assertInstanceOf(TestSessions::class, $container->make(SessionStore::class));
		$this->assertInstanceOf(Accounts::class, $container->make(Accounts::class), 'Other areas keep the default.');
	}

	public function testADriversOwnToolsComeWithIt(): void
	{
		$container = $this->scratchApplication()->container();

		$this->assertTrue($container->make(CommandRegistry::class)->has('content:ids'), 'Content kept as files has its tools (D-654).');

		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'memory']);\n");

		$container = $this->scratchApplication()->container();
		$container->make(StorageDriverRegistry::class)->register('memory', SessionsOnly::class);

		$registry = $container->make(CommandRegistry::class);

		$this->assertFalse($registry->has('content:ids'), 'Content kept elsewhere doesn\'t.');
		$this->assertFalse($registry->has('content:filenames'));
		$this->assertTrue($registry->has('content:lint'), 'Built-in commands stay.');
	}

	public function testSqliteKeepsContentRecordsInTheSitesDatabase(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'sqlite']);\n");

		$container = $this->scratchApplication()->container();
		$store     = $container->make(RecordStores::class)->store(EntryTable::table());

		$this->assertInstanceOf(SqliteRecordStore::class, $store);
		$this->assertSame($store, $container->make(RecordStores::class)->store(Ref::table(StorageArea::Content)), 'One store for the database.');
		$this->assertInstanceOf(RecordLocations::class, $container->make(EntryLocations::class));
		$this->assertInstanceOf(Accounts::class, $container->make(Accounts::class), 'Other areas keep theirs.');
		$this->assertFalse($container->make(CommandRegistry::class)->has('content:ids'), 'File tools aren\'t offered.');

		$store->find(EntryTable::table(), '0198c0de-0000-7000-8000-000000000000');

		$this->assertFileExists($this->temporaryDirectory() . '/user/site.sqlite', 'In user/ by default (D-662).');
	}

	public function testADriverWithoutAContractFails(): void
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['accounts' => 'memory']);\n");

		$container = $this->scratchApplication()->container();
		$container->make(StorageDriverRegistry::class)->register('memory', SessionsOnly::class);

		$this->expectException(StorageException::class);
		$this->expectExceptionMessage(sprintf('The "memory" storage driver, named for accounts, has no %s.', RecordStore::class));

		$container->make(RecordStores::class)->store(Accounts::table());
	}

	public function testAnUnknownDriverFails(): void
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'nowhere']);\n");

		$container = $this->scratchApplication()->container();

		$this->expectException(StorageException::class);
		$this->expectExceptionMessage('Unknown storage driver "nowhere"; registered drivers: filesystem, sqlite.');

		$container->make(ContentSource::class);
	}

	public function testTheSavedSettingsNeedABuiltInDataDriver(): void
	{
		$this->expectException(StorageException::class);
		$this->expectExceptionMessage('Unknown storage driver "nowhere" for data');

		$this->scratchApplication(['STORAGE_DRIVER' => 'nowhere']);
	}

	public function testTheRegistrarKeepsAnExtensionsName(): void
	{
		$registry = new StorageDriverRegistry(['filesystem' => SessionsOnly::class]);
		new StorageDriverRegistrar($registry)->register();

		$this->assertSame(SessionsOnly::class, $registry->get('filesystem'));
	}
}

/**
 * A driver an extension might register for sessions alone (D-640).
 */
final readonly class SessionsOnly implements Storage
{
	#[Override]
	public function bindings(): array
	{
		return [SessionStore::class => TestSessions::class];
	}

	#[Override]
	public function commands(StorageArea $area): array
	{
		return [];
	}
}

final class TestSessions implements SessionStore
{
	#[Override]
	public function read(string $id): ?array
	{
		return null;
	}

	#[Override]
	public function write(string $id, array $record): void
	{
	}

	#[Override]
	public function delete(string $id): void
	{
	}

	#[Override]
	public function prune(int $before): int
	{
		return 0;
	}
}
