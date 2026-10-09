<?php

/**
 * SQLite areas test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use DateTimeImmutable;
use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Data\DataStore;
use Blush\Data\InvalidData;
use Blush\Data\RecordDataStore;
use Blush\Feed\FeedConfig;
use Blush\Job\JobRecord;
use Blush\Job\JobStatus;
use Blush\Job\JobStore;
use Blush\Job\RecordJobStore;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaResolver;
use Blush\Session\RecordSessionStore;
use Blush\Session\SessionStore;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Storage\SqliteStorage;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Support\Uuid;
use Blush\Tests\BootsScratchSite;

/**
 * A site with every area on the SQLite driver (D-662): data, accounts,
 * roles, sessions, and jobs kept in `user/site.sqlite`, each answering
 * its contract as the filesystem's stores do.
 */
#[CoversClass(SqliteStorage::class)]
#[CoversClass(RecordDataStore::class)]
#[CoversClass(Accounts::class)]
#[CoversClass(RecordSessionStore::class)]
#[CoversClass(RecordJobStore::class)]
final class SqliteAreasTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	protected function setUp(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->app = $this->boot();
	}

	/**
	 * Boots the scratch site with every area on SQLite.
	 */
	private function boot(): Application
	{
		$app = $this->scratchApplication(['STORAGE_DRIVER' => 'sqlite', 'APP_ENV' => 'production']);
		$app->boot();

		return $app;
	}

	/**
	 * Returns a service.
	 *
	 * @template T of object
	 * @param  class-string<T> $class
	 * @return T
	 */
	private function make(string $class): object
	{
		return $this->app->container()->make($class);
	}

	public function testEveryAreaIsKeptInTheSitesDatabase(): void
	{
		$this->assertInstanceOf(RecordDataStore::class, $this->make(DataStore::class));
		$this->assertInstanceOf(Accounts::class, $this->make(Accounts::class));
		$this->assertInstanceOf(RecordSessionStore::class, $this->make(SessionStore::class));
		$this->assertInstanceOf(RecordJobStore::class, $this->make(JobStore::class));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/data', 'Nothing kept as files.');
	}

	public function testKeepsMediaMetadataAsRecords(): void
	{
		$this->writeTemporaryFile('user/media/2026/lake.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));

		$file  = $this->make(MediaResolver::class)->fromKey('2026/lake.png');
		$store = $this->make(MediaMetadataStore::class);

		$this->assertNotNull($file);

		$store->save($file, ['alt' => 'A lake', 'content' => 'Long.']);

		$metadata = $store->find($file);

		$this->assertSame('A lake', $metadata->alt);
		$this->assertSame('Long.', $metadata->values['content'] ?? null, 'Its description is the record\'s content (D-674).');
		$this->assertNotSame('', $metadata->id, 'A record has its id.');
		$this->assertSame(['2026/lake.png'], array_keys($store->files()));
		$this->assertSame('media/2026/lake.png in the database', $store->location('2026/lake.png'));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/data/media', 'Kept in the database (D-675).');

		$store->forget('2026/lake.png');

		$this->assertSame('', $store->find($file)->alt);
	}

	public function testKeepsDataByName(): void
	{
		$data = $this->make(DataStore::class);

		$this->assertFalse($data->has('types/post'));
		$this->assertNull($data->load('types/post'), 'A missing record is null, not empty.');

		$data->save('types/post', ['$schema' => 'type.schema.json', 'labels' => ['singular' => 'Café']]);
		$data->save('media/a.png', ['alt' => 'A']);
		$data->save('media/2026/b.png', ['alt' => 'B']);

		$this->assertTrue($data->has('types/post'));
		$this->assertSame(['labels' => ['singular' => 'Café']], $data->load('types/post'), 'An editor\'s schema key isn\'t data (D-491).');
		$this->assertSame(['a.png' => ['alt' => 'A']], $data->loadAll('media'), 'Only the records directly in it.');
		$this->assertSame(['2026/b.png', 'a.png'], array_keys($data->records('media')), 'Every record under it.');
		$this->assertSame([], $data->loadAll('missing'));
		$this->assertSame('types/post in the database', $data->location('types/post'));

		$data->delete('types/post');
		$data->delete('types/post');

		$this->assertFalse($data->has('types/post'), 'Deleting a missing record is nothing to do.');

		$this->expectException(InvalidData::class);

		$data->load('../config');
	}

	public function testAFailedDataTransactionPutsEveryRecordBack(): void
	{
		$data = $this->make(DataStore::class);
		$data->save('types/post', ['icon' => 'pen']);

		try {
			$data->transaction(static function () use ($data): void {
				$data->save('types/post', ['icon' => 'book']);
				$data->save('relations/tags', ['kind' => 'classify']);

				throw new RuntimeException('Doesn\'t fit.');
			});
		} catch (RuntimeException) {
		}

		$this->assertSame(['icon' => 'pen'], $data->load('types/post'));
		$this->assertFalse($data->has('relations/tags'));
	}

	public function testTheSavedSettingsAreReadBeforeTheContainer(): void
	{
		$this->make(SettingsStore::class)->update(static fn (Settings $settings): Settings => $settings->with(['app.name' => 'Notes', 'feed.limit' => 7]));

		$container = $this->boot()->container();

		$this->assertSame('Notes', $container->make(AppConfig::class)->name, 'Read by the bootstrap from the database.');
		$this->assertSame(7, $container->make(FeedConfig::class)->limit, 'A group read when its config is first asked for (D-673).');
	}

	public function testKeepsAccountsAndRoles(): void
	{
		$accounts = $this->make(Accounts::class);
		$store    = $accounts;

		$this->assertTrue($store->isEmpty());

		$accounts->create('jane', 'correct horse battery staple', ['editor'], email: 'jane@example.test');
		$accounts->create('abe', 'correct horse battery staple', ['author'], email: 'abe@example.test');

		$this->assertFalse($store->isEmpty());
		$this->assertSame(['abe', 'jane'], array_map(static fn ($account): string => $account->username, $store->all()));
		$this->assertSame('jane@example.test', $store->find('jane')?->email);
		$this->assertNull($store->find('../jane'));

		$store->delete('abe');

		$this->assertNull($store->find('abe'));

		$roles = $this->make(Roles::class);
		$roles->save([...$roles->stored(), new Role('reviewer', 'Reviewer', [])]);

		$this->assertContains('reviewer', array_map(static fn (Role $role): string => $role->name, $this->boot()->container()->make(Roles::class)->all()));
	}

	public function testKeepsSessionsByAHashOfTheirIds(): void
	{
		$sessions = $this->make(SessionStore::class);
		$record   = ['created' => 100, 'lastSeen' => 200, 'data' => ['user' => 'jane']];

		$sessions->write('abc', $record);

		$this->assertSame($record, $sessions->read('abc'));
		$this->assertNull($sessions->read('other'));
		$this->assertSame(0, $sessions->prune(0), 'Written just now.');
		$this->assertSame(1, $sessions->prune(time() + 60));
		$this->assertNull($sessions->read('abc'));

		$sessions->write('abc', $record);
		$sessions->delete('abc');

		$this->assertNull($sessions->read('abc'));
	}

	public function testKeepsJobsAndClaimsEachOnce(): void
	{
		$jobs   = $this->make(JobStore::class);
		$first  = new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-01')), 'test/count');
		$second = new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-02')), 'test/count');

		$jobs->save($second);
		$jobs->save($first);

		$this->assertSame([$first->id, $second->id], array_map(static fn (JobRecord $job): string => $job->id, $jobs->all()), 'Oldest first.');
		$this->assertSame('test/count', $jobs->find($first->id)?->job);

		$claimed = $jobs->claim($first, $first->with(['started' => 5]));

		$this->assertSame(JobStatus::Running, $claimed?->status);
		$this->assertNull($jobs->claim($first, $first), 'Never twice.');
		$this->assertSame([$second->id], array_map(static fn (JobRecord $job): string => $job->id, $jobs->all(JobStatus::Queued)));

		$jobs->delete($first->id);

		$this->assertNull($jobs->find($first->id));

		$jobs->saveState('runners', ['cron' => 10]);

		$this->assertSame(['cron' => 10], $jobs->state('runners'));
		$this->assertSame([], $jobs->state('missing'));

		$ran = false;

		$this->assertTrue($jobs->locked('schedule', static function () use (&$ran): void {
			$ran = true;
		}));
		$this->assertTrue($ran);
	}
}
