<?php

/**
 * Restore test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Backup;

use DateTimeImmutable;
use ZipArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Backup\Backup;
use Blush\Backup\BackupBusy;
use Blush\Backup\BackupException;
use Blush\Backup\BackupKind;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupRestorer;
use Blush\Backup\Backups;
use Blush\Backup\FilesystemTableBackup;
use Blush\Backup\RestoreCheck;
use Blush\Backup\RestoreJob;
use Blush\Backup\SqliteTableBackup;
use Blush\Console\Commands\RestoreBackup;
use Blush\Console\Console;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobStatus;
use Blush\Job\JobStore;
use Blush\Session\SessionStore;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Support\Uuid;
use Blush\Tests\Content\BuildsContentSite;

/**
 * Restoring parts of a backup (D-886): the site backed up first, so it
 * can be undone; each driver's tables replaced and nothing else; what
 * refuses a restore said first; everyone signed out when accounts come
 * back; and an archive that would write outside its folder refused.
 */
#[CoversClass(BackupRestorer::class)]
#[CoversClass(RestoreCheck::class)]
#[CoversClass(RestoreJob::class)]
#[CoversClass(FilesystemTableBackup::class)]
#[CoversClass(SqliteTableBackup::class)]
#[CoversClass(RestoreBackup::class)]
final class RestoreTest extends TestCase
{
	use BuildsContentSite;

	private const string WELCOME = 'user/content/_post/2003-04-15.welcome.md';

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/old-about", "to": "/about"}]');
		$this->app = $this->site();
		$this->app->container()->make(Accounts::class)->create('jane', 'correct horse battery staple', ['editor'], email: 'jane@example.test');
	}

	/**
	 * Runs a command on a site.
	 */
	private static function command(Application $app, string $command): CommandResult
	{
		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	/**
	 * Backs up every part, and returns the backup.
	 */
	private function backUp(Application $app): Backup
	{
		return $app->container()->make(BackupMaker::class)->make($app->container()->make(\Blush\Backup\BackupParts::class)->names());
	}

	/**
	 * Changes the site after a backup: a post edited, one removed, one
	 * added, and the redirects replaced.
	 */
	private function change(): void
	{
		$root = $this->temporaryDirectory();

		file_put_contents("{$root}/" . self::WELCOME, (string) str_replace('Hello and welcome', 'Changed', (string) file_get_contents("{$root}/" . self::WELCOME)));
		unlink("{$root}/user/content/notes.md");
		$this->writeTemporaryFile('user/content/_post/2026-10-16.new.md', "---\ntitle: New\n---\nAfter the backup.");
		$this->writeTemporaryFile('user/data/redirects.json', '[]');
	}

	public function testRestoresPartsOfAFlatFileSiteAndCanBeUndone(): void
	{
		$root     = $this->temporaryDirectory();
		$backup   = $this->backUp($this->app);
		$restorer = $this->app->container()->make(BackupRestorer::class);

		$this->change();

		$safety = $restorer->restore($backup, ['content', 'redirects']);

		$this->assertStringContainsString('Hello and welcome', (string) file_get_contents("{$root}/" . self::WELCOME));
		$this->assertFileExists("{$root}/user/content/notes.md", 'What was removed is back.');
		$this->assertFileDoesNotExist("{$root}/user/content/_post/2026-10-16.new.md", 'What was added since is gone.');
		$this->assertStringContainsString('/old-about', (string) file_get_contents("{$root}/user/data/redirects.json"));
		$this->assertNotNull($this->app->container()->make(Accounts::class)->find('jane'), 'Parts not picked stay as they are.');

		$this->assertSame(BackupKind::BeforeRestore, $safety->kind);
		$this->assertSame(['content', 'redirects'], $safety->partNames());
		$this->assertSame("Before restoring {$backup->name}", $safety->note);

		$restorer->restore($safety, ['content', 'redirects']);

		$this->assertStringContainsString('Changed', (string) file_get_contents("{$root}/" . self::WELCOME), 'Undone.');
		$this->assertFileExists("{$root}/user/content/_post/2026-10-16.new.md");
		$this->assertDirectoryDoesNotExist($this->app->container()->make(Backups::class)->folder() . '/' . Backups::RESTORE . "/{$backup->name}", 'Its work is cleaned up.');
	}

	public function testRestoringAccountsSignsEveryoneOut(): void
	{
		$container = $this->app->container();
		$backup    = $this->backUp($this->app);

		$container->make(Accounts::class)->create('bob', 'another long enough password', ['author'], email: 'bob@example.test');
		$container->make(SessionStore::class)->write('a-session-key', ['created' => 0, 'lastSeen' => time(), 'data' => []]);

		$check = $container->make(BackupRestorer::class)->check($backup, ['accounts']);

		$this->assertTrue($check->allowed());
		$this->assertStringContainsString('signs everyone out', (string) ($check->warnings[0] ?? ''));

		$container->make(BackupRestorer::class)->restore($backup, ['accounts']);

		$this->assertNull($container->make(Accounts::class)->find('bob'), 'An account made since is gone.');
		$this->assertNotNull($container->make(Accounts::class)->find('jane'));
		$this->assertNull($container->make(SessionStore::class)->read('a-session-key'));
	}

	public function testSaysWhatRefusesARestore(): void
	{
		$container = $this->app->container();
		$backup    = $this->backUp($this->app);
		$restorer  = $container->make(BackupRestorer::class);

		$this->assertFalse($restorer->check($backup, [])->allowed());
		$this->assertFalse($restorer->check($backup, ['gallery'])->allowed());
		$this->assertFalse($restorer->check($backup->with(['formatVersion' => Backup::FORMAT_VERSION + 1]), ['content'])->allowed(), 'A newer format.');
		$this->assertFalse($restorer->check($backup->with(['blushVersion' => '99.0.0']), ['content'])->allowed(), 'A newer Blush.');
		$this->assertFalse($restorer->check($backup->with(['formatVersion' => 0]), ['content'])->allowed(), 'From before backups were versioned.');

		$other = $restorer->check($backup->with(['drivers' => [...$backup->drivers, 'content' => 'sqlite']]), ['content', 'redirects']);

		$this->assertCount(1, $other->problems);
		$this->assertStringContainsString('storage:copy', (string) $other->problems[0], 'Moving between drivers is storage:copy.');

		$links = $restorer->check($backup, ['content']);

		$this->assertTrue($links->allowed());
		$this->assertStringContainsString('types and relationships the site has now', (string) ($links->warnings[0] ?? ''));

		$container->make(BackupMaker::class)->start(['roles']);

		$this->assertFalse($restorer->check($backup, ['content'])->allowed(), 'Not while a backup is being made.');
	}

	public function testRestoresOneAtATime(): void
	{
		$container = $this->app->container();
		$backup    = $this->backUp($this->app);

		$this->change();

		$job = $container->make(BackupRestorer::class)->start($backup, ['content']);

		$this->assertSame($job->id, $container->make(BackupRestorer::class)->running()?->id);

		$this->expectException(BackupBusy::class);
		$container->make(BackupRestorer::class)->start($backup, ['content']);
	}

	public function testRunsTheRestoreJobToItsEnd(): void
	{
		$container = $this->app->container();
		$backup    = $this->backUp($this->app);

		$this->change();

		$record = $container->make(BackupRestorer::class)->start($backup, ['redirects']);
		$data   = $record->data;

		for ($runs = 0; $runs < 30; $runs++) {
			$result = $container->make(RestoreJob::class)->handle($record->with(['data' => $data]));

			if ($result->status !== JobStatus::Queued) {
				break;
			}

			$data = $result->data;
		}

		$this->assertSame(JobStatus::Done, $result->status);
		$this->assertIsString($result->result['safety'] ?? null);
		$this->assertStringContainsString('/old-about', (string) file_get_contents($this->temporaryDirectory() . '/user/data/redirects.json'));
	}

	public function testRefusesAnArchiveThatWritesOutsideItsFolder(): void
	{
		$backups = $this->app->container()->make(Backups::class);
		$backup  = $this->backUp($this->app);
		$archive = $backups->archive($backup) ?? self::fail('No archive.');

		$zip = new ZipArchive();
		$zip->open($archive);
		$zip->addFromString('filesystem/../../escaped.txt', 'out');
		$zip->close();

		try {
			$this->app->container()->make(BackupRestorer::class)->restore($backup, ['redirects']);
			self::fail('The restore went ahead.');
		} catch (BackupException $e) {
			$this->assertStringContainsString('holds a file it shouldn\'t', $e->getMessage());
		}

		$this->assertFileDoesNotExist($backups->folder() . '/escaped.txt');
		$this->assertFileDoesNotExist($backups->folder() . '/.restore/escaped.txt');
	}

	public function testRestoresFromTheCommandLine(): void
	{
		$backup = $this->backUp($this->app);

		$this->change();

		$restored = self::command($this->app, "backup:restore {$backup->name} --parts=redirects --yes");

		$this->assertTrue($restored->isSuccessful(), $restored->output . $restored->errors);
		$this->assertStringContainsString('To undo it, restore the backup made first', $restored->output);
		$this->assertStringContainsString('/old-about', (string) file_get_contents($this->temporaryDirectory() . '/user/data/redirects.json'));
		$this->assertFalse(self::command($this->app, 'backup:restore nothing-here --yes')->isSuccessful());
	}

	public function testRestoresASqliteSitesTablesAndKeepsUnfinishedJobs(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig('sqlite');\n");
		$this->assertTrue(self::command($this->app, 'storage:copy --from=filesystem --to=sqlite')->isSuccessful());

		$app       = $this->site();
		$container = $app->container();
		$backup    = $this->backUp($app);
		$waiting   = new JobRecord(Uuid::v7(new DateTimeImmutable('2026-10-16')), 'test/waiting');

		$container->make(Accounts::class)->create('bob', 'another long enough password', ['author'], email: 'bob@example.test');
		$container->make(JobStore::class)->save($waiting);

		$container->make(BackupRestorer::class)->restore($backup, ['accounts', 'jobs']);

		$this->assertNull($container->make(Accounts::class)->find('bob'));
		$this->assertNotNull($container->make(Accounts::class)->find('jane'));
		$this->assertNotNull($container->make(JobQueue::class)->find($waiting->id), 'A job that hadn\'t run is left to run.');
	}
}
