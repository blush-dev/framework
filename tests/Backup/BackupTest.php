<?php

/**
 * Backup test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Backup;

use ArrayObject;
use DateTimeImmutable;
use PDO;
use ZipArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Backup\Backup;
use Blush\Backup\BackupBusy;
use Blush\Backup\BackupException;
use Blush\Backup\BackupJob;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupParts;
use Blush\Backup\Backups;
use Blush\Backup\BackupStatus;
use Blush\Backup\Events\BackupMade;
use Blush\Backup\FilesystemTableBackup;
use Blush\Backup\SqliteTableBackup;
use Blush\Console\Commands\DeleteBackup;
use Blush\Console\Commands\ListBackups;
use Blush\Console\Commands\MakeBackup;
use Blush\Console\Console;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobStatus;
use Blush\Job\JobStore;
use Blush\Session\SessionStore;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Support\Uuid;
use Blush\Tests\Content\BuildsContentSite;

/**
 * Making, listing, and deleting backups (D-881 to D-886): each driver's
 * tables in its own form, the parts asked for and nothing else, sessions
 * and unfinished jobs never, a chunk at a time as a job, and deleting
 * with a way back.
 */
#[CoversClass(Backup::class)]
#[CoversClass(BackupMaker::class)]
#[CoversClass(BackupParts::class)]
#[CoversClass(Backups::class)]
#[CoversClass(BackupJob::class)]
#[CoversClass(FilesystemTableBackup::class)]
#[CoversClass(SqliteTableBackup::class)]
#[CoversClass(BackupMade::class)]
#[CoversClass(MakeBackup::class)]
#[CoversClass(ListBackups::class)]
#[CoversClass(DeleteBackup::class)]
final class BackupTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/old-about", "to": "/about"}]');
		$this->app = $this->site();
		$this->seed($this->app);
	}

	/**
	 * Adds an account, a session, and a finished and a queued job.
	 */
	private function seed(Application $app): void
	{
		$container = $app->container();
		$container->make(Accounts::class)->create('jane', 'correct horse battery staple', ['editor'], email: 'jane@example.test');
		$container->make(SessionStore::class)->write('a-session-key', ['created' => 0, 'lastSeen' => 0, 'data' => ['account' => 'jane']]);

		$jobs = $container->make(JobStore::class);
		$jobs->save(new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-01')), 'test/done', status: JobStatus::Done));
		$jobs->save(new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-02')), 'test/waiting'));
	}

	/**
	 * Runs a command on a site.
	 */
	private static function command(Application $app, string $command): CommandResult
	{
		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	/**
	 * Returns the names of the files in an archive.
	 *
	 * @return list<string>
	 */
	private static function entries(string $archive): array
	{
		$zip = new ZipArchive();
		self::assertTrue($zip->open($archive) === true);

		$names = [];

		for ($index = 0; $index < $zip->numFiles; $index++) {
			$names[] = (string) $zip->getNameIndex($index);
		}

		$zip->close();
		sort($names);

		return $names;
	}

	/**
	 * Returns a query's first column, as text.
	 *
	 * @return list<string>
	 */
	private static function column(PDO $pdo, string $sql): array
	{
		$statement = $pdo->query($sql);

		self::assertNotFalse($statement);

		return array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $statement->fetchAll(PDO::FETCH_COLUMN)));
	}

	/**
	 * Returns the only backup's archive.
	 */
	private function archive(Application $app): string
	{
		$backups = $app->container()->make(Backups::class);
		$all     = $backups->all();

		$this->assertCount(1, $all);

		return $backups->archive($all[0]) ?? self::fail('The backup has no archive.');
	}

	public function testBacksUpEveryPartOfAFlatFileSiteAsItsFiles(): void
	{
		$made = self::command($this->app, 'backup:make --note=Moving');

		$this->assertTrue($made->isSuccessful(), $made->output . $made->errors);
		$this->assertStringContainsString('storage/backups/site/blush-', $made->output);

		$names = self::entries($this->archive($this->app));

		$this->assertContains('backup.json', $names);
		$this->assertContains('filesystem/user/content/_post/2008-04-05.spring.md', $names, 'Content as its files.');
		$this->assertContains('filesystem/user/data/redirects.json', $names);
		$this->assertNotEmpty(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'filesystem/storage/accounts/')), 'Accounts.');
		$this->assertNotEmpty(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'filesystem/storage/jobs/done/')), 'Finished jobs.');
		$this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'filesystem/storage/jobs/queued/'))), 'Never a job that would run again.');
		$this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_contains($name, 'sessions') || str_contains($name, 'secrets'))), 'Never sessions or secrets.');

		$backup = $this->app->container()->make(Backups::class)->all()[0];

		$this->assertSame(BackupStatus::Done, $backup->status);
		$this->assertSame('Moving', $backup->note);
		$this->assertEquals(['content' => 'filesystem', 'data' => 'filesystem', 'accounts' => 'filesystem', 'jobs' => 'filesystem'], array_intersect_key($backup->drivers, ['content' => 1, 'data' => 1, 'accounts' => 1, 'jobs' => 1]));
		$this->assertGreaterThan(0, $backup->parts['content']['count'] ?? 0);
		$this->assertSame(1, $backup->parts['accounts']['count'] ?? null);

		$listed = self::command($this->app, 'backup:list');

		$this->assertStringContainsString($backup->name, $listed->output);
		$this->assertStringContainsString('everything', $listed->output);
	}

	public function testBacksUpOnlyThePartsAskedFor(): void
	{
		$made = self::command($this->app, 'backup:make --parts=accounts,redirects');

		$this->assertTrue($made->isSuccessful(), $made->output . $made->errors);

		$names    = self::entries($this->archive($this->app));
		$accounts = array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'filesystem/storage/accounts/')));

		$this->assertCount(1, $accounts);
		$this->assertSame(['backup.json', $accounts[0], 'filesystem/user/data/redirects.json'], $names);
		$this->assertSame(['redirects', 'accounts'], $this->app->container()->make(Backups::class)->all()[0]->partNames(), 'In the order parts are listed.');
	}

	public function testRefusesAnUnknownPartOrNone(): void
	{
		$maker = $this->app->container()->make(BackupMaker::class);

		$this->assertTrue(! self::command($this->app, 'backup:make --parts=gallery')->isSuccessful());

		$this->expectException(BackupException::class);
		$maker->make([]);
	}

	public function testBacksUpASqliteSiteAsADatabaseOfItsParts(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig('sqlite');\n");
		$this->assertTrue(self::command($this->app, 'storage:copy --from=filesystem --to=sqlite')->isSuccessful());

		$app = $this->site();
		$app->container()->make(SessionStore::class)->write('a-session-key', ['created' => 0, 'lastSeen' => 0, 'data' => ['account' => 'jane']]);
		$app->container()->make(JobStore::class)->save(new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-03')), 'test/waiting'));

		$made = self::command($app, 'backup:make --parts=content,accounts,jobs');

		$this->assertTrue($made->isSuccessful(), $made->output . $made->errors);

		$archive = $this->archive($app);

		$this->assertSame(['backup.json', 'sqlite/site.sqlite'], self::entries($archive));

		$zip = new ZipArchive();
		$zip->open($archive);
		$copy = $this->temporaryDirectory() . '/copy.sqlite';
		file_put_contents($copy, (string) $zip->getFromName('sqlite/site.sqlite'));
		$zip->close();

		$pdo    = SqliteConnection::open($copy);
		$tables = self::column($pdo, "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");

		$this->assertSame(['accounts:accounts', 'content:entries', 'content:refs', 'jobs:job_state', 'jobs:jobs'], array_values(array_intersect(['accounts:accounts', 'accounts:roles', 'content:entries', 'content:refs', 'jobs:job_state', 'jobs:jobs', 'sessions:sessions'], $tables)), 'Only the parts asked for: no roles, no sessions.');
		$this->assertSame(['0'], self::column($pdo, "SELECT COUNT(*) FROM \"jobs:jobs\" WHERE json_extract(fields, '$.status') = 'queued'"), 'No job that would run again.');
		$this->assertNotSame(['0'], self::column($pdo, 'SELECT COUNT(*) FROM "content:entries"'));
	}

	public function testMakesABackupAsAJobAChunkAtATime(): void
	{
		$container = $this->app->container();
		$maker     = $container->make(BackupMaker::class);
		$backup    = $maker->start(['content', 'settings']);

		$this->assertSame(BackupStatus::Running, $backup->status);
		$this->assertNotNull($backup->job);

		$this->expectExceptionObject(new BackupBusy('A backup is being made or restored. Wait for it to finish first.'));

		try {
			$maker->start(['roles']);
		} finally {
			$job   = $container->make(JobQueue::class)->find((string) $backup->job);
			$state = [];

			$this->assertNotNull($job);

			for ($runs = 0; $runs < 20; $runs++) {
				$result = $container->make(BackupJob::class)->handle($job->with(['data' => ['name' => $backup->name, 'state' => $state]]));

				if ($result->status !== JobStatus::Queued) {
					break;
				}

				$state = is_array($result->data['state'] ?? null) ? $result->data['state'] : [];
			}

			$this->assertSame(JobStatus::Done, $result->status);
			$this->assertSame(BackupStatus::Done, $container->make(Backups::class)->find($backup->name)?->status);
		}
	}

	public function testCancelsABackupBeingMade(): void
	{
		$container = $this->app->container();
		$backups   = $container->make(Backups::class);
		$backup    = $container->make(BackupMaker::class)->start(['content']);

		$backups->delete($backup);
		$container->make(BackupMaker::class)->step($backup->name, []);

		$this->assertSame([], $backups->all());
		$this->assertDirectoryDoesNotExist($backups->work($backup->name));
	}

	public function testDeletingABackupCanBeUndone(): void
	{
		$this->assertTrue(self::command($this->app, 'backup:make --parts=roles')->isSuccessful());

		$backups = $this->app->container()->make(Backups::class);
		$backup  = $backups->all()[0];

		$this->assertTrue(self::command($this->app, "backup:delete {$backup->name}")->isSuccessful());
		$this->assertSame([], $backups->all());

		$back = $backups->restoreDeleted($backup->name);

		$this->assertSame($backup->name, $back->name);
		$this->assertNotNull($backups->archive($back));
	}

	public function testNotesAreTrimmedToTheirLength(): void
	{
		$this->assertTrue(self::command($this->app, 'backup:make --parts=roles')->isSuccessful());

		$backups = $this->app->container()->make(Backups::class);
		$noted   = $backups->note($backups->all()[0], str_repeat('a', 80));

		$this->assertSame(Backup::NOTE_LENGTH, mb_strlen($noted->note));
		$this->assertSame($noted->note, $backups->all()[0]->note);
	}

	public function testListsAZipCopiedInByHand(): void
	{
		$this->assertTrue(self::command($this->app, 'backup:make --parts=roles')->isSuccessful());

		$backups = $this->app->container()->make(Backups::class);
		$backup  = $backups->all()[0];

		unlink($backups->folder() . "/{$backup->name}.json");

		$this->assertSame($backup->name, $this->site()->container()->make(Backups::class)->all()[0]->name ?? null);
		$this->assertFileExists($backups->folder() . "/{$backup->name}.json");
	}

	public function testAnnouncesEachBackupMade(): void
	{
		$made = new ArrayObject();

		$this->app->container()->make(ListenerRegistry::class)->listen(BackupMade::class, static function (BackupMade $event) use ($made): void {
			$made->append([$event->backup->name, $event->backup->status, is_file($event->file)]);
		});

		$backup = $this->app->container()->make(BackupMaker::class)->make(['roles']);

		$this->assertSame([[$backup->name, BackupStatus::Done, true]], $made->getArrayCopy(), 'Once it\'s kept, with its archive.');
	}
}
