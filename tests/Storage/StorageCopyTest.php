<?php

/**
 * Storage copy test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Console\Commands\CopyStorage;
use Blush\Console\Commands\SyncStorage;
use Blush\Console\Console;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Entries;
use Blush\Core\Application;
use Blush\Data\DataStore;
use Blush\Job\JobRecord;
use Blush\Job\JobStore;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\StorageCopy;
use Blush\Support\Uuid;
use Blush\Tests\Content\BuildsContentSite;
use DateTimeImmutable;

/**
 * Copying a flat-file site into SQLite (D-662): everything kept by id,
 * each entry's front matter as written, and the site answering the same
 * once it names the new driver.
 */
#[CoversClass(StorageCopy::class)]
#[CoversClass(CopyStorage::class)]
#[CoversClass(SyncStorage::class)]
final class StorageCopyTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->standardContent();
		$this->writeTemporaryFile('user/content/_posts/2009-01-01.no-id.md', "---\ntitle: No Id\n---\nA file without an id.");
		$this->app = $this->site();

		$container = $this->app->container();
		$container->make(DataStore::class)->save('menus/main', ['label' => 'Main']);
		$container->make(Accounts::class)->create('jane', 'correct horse battery staple', ['editor'], email: 'jane@example.test');
		$roles = $container->make(Roles::class);
		$roles->save([...$roles->stored(), new Role('reviewer', 'Reviewer')]);
		$container->make(JobStore::class)->save(new JobRecord(Uuid::v7(new DateTimeImmutable('2026-01-01')), 'test/count'));
	}

	/**
	 * Runs a command on a site.
	 */
	private static function command(Application $app, string $command): CommandResult
	{
		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	/**
	 * Boots the site again with every area on SQLite.
	 */
	private function onSqlite(): Application
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig('sqlite');\n");

		return $this->site();
	}

	public function testCopiesASiteFromFilesIntoSqlite(): void
	{
		$copied = self::command($this->app, 'storage:copy --from=filesystem --to=sqlite');

		$this->assertTrue($copied->isSuccessful(), $copied->output . $copied->errors);
		$this->assertStringContainsString('Copied 1 accounts.', $copied->output);
		$this->assertStringContainsString('1 content file without an id weren\'t copied', $copied->output . $copied->errors);

		$jane   = $this->app->container()->make(Accounts::class)->find('jane')?->id;
		$files  = $this->app->container()->make(Entries::class);
		$app    = $this->onSqlite();
		$sqlite = $app->container()->make(Entries::class);
		$spring = $sqlite->named('post', 'spring');

		$this->assertNotNull($spring);
		$this->assertSame($files->named('post', 'spring')?->id, $spring->id, 'Ids kept.');
		$this->assertSame(['art', 'book-reviews'], $spring->terms('category'));
		$this->assertSame('Spring is here.', trim($sqlite->editable($spring)->body));
		$welcome = (string) $files->named('post', 'welcome')?->id;
		$front   = $sqlite->editable($welcome)->frontMatter;

		$this->assertSame('2003-04-15T17:39:00-05:00', $front['date'] ?? null, 'Front matter as the file\'s editor reads it, alias and all.');
		$this->assertSame('Welcome', $front['title'] ?? null);
		$this->assertSame(['old-posts'], $front['category'] ?? null, 'Relations from refs, by their slugs now.');
		$this->assertSame(['justintadlock'], $front['authors'] ?? null, 'Under the field\'s name.');
		$this->assertSame(
			array_map(static fn ($entry): ?string => $entry->id, $files->query()->type('post')->get()->all()),
			array_map(static fn ($entry): ?string => $entry->id, $sqlite->query()->type('post')->get()->all()),
			'The same answers.'
		);
		$this->assertNull($sqlite->named('post', 'no-id'));

		$container = $app->container();

		$this->assertSame(['label' => 'Main'], $container->make(DataStore::class)->load('menus/main'));
		$this->assertSame('jane@example.test', $container->make(Accounts::class)->find('jane')?->email);
		$this->assertSame('jane', $container->make(Accounts::class)->findById((string) $jane)?->username, 'Accounts keep their ids, which links hold (D-668).');
		$this->assertContains('reviewer', array_map(static fn (Role $role): string => $role->name, $container->make(Roles::class)->stored()));
		$this->assertCount(1, $container->make(JobStore::class)->all());
	}

	public function testRefusesToCopyOverRecordsUnlessReplacing(): void
	{
		$this->assertTrue(self::command($this->app, 'storage:copy')->isSuccessful(), 'Files into SQLite by default.');

		$again = self::command($this->app, 'storage:copy');

		$this->assertFalse($again->isSuccessful());
		$this->assertStringContainsString('The database already holds records', $again->output . $again->errors);
		$this->assertTrue(self::command($this->app, 'storage:copy --replace')->isSuccessful());

		$other = self::command($this->app, 'storage:copy --from=sqlite --to=filesystem');

		$this->assertFalse($other->isSuccessful());
		$this->assertStringContainsString('isn\'t supported yet', $other->output . $other->errors);
	}

	public function testSyncMakesTheTablesADatabaseKeeps(): void
	{
		$files = self::command($this->app, 'storage:sync');

		$this->assertStringContainsString('Nothing to do', $files->output);

		$synced = self::command($this->onSqlite(), 'storage:sync');

		$this->assertTrue($synced->isSuccessful());
		$this->assertStringContainsString('Made or updated 11 tables.', $synced->output, 'Types, relations, and settings are tables of their own (D-672, D-673).');
	}
}
