<?php

/**
 * Content command tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Activity\ActivityFilter;
use Blush\Activity\ActivityLog;
use Blush\Activity\Via;
use Blush\Auth\Accounts;
use Blush\Console\Commands\CreateContent;
use Blush\Console\Commands\FixIds;
use Blush\Console\Commands\MigrateTypeFolders;
use Blush\Console\Commands\MoveToFolders;
use Blush\Console\Commands\IndexContent;
use Blush\Console\Commands\LintContent;
use Blush\Console\Commands\ListContent;
use Blush\Console\Commands\RenameToPattern;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Content\EntryIdReport;
use Blush\Content\EntryIds;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(IndexContent::class)]
#[CoversClass(LintContent::class)]
#[CoversClass(ListContent::class)]
#[CoversClass(CreateContent::class)]
#[CoversClass(FixIds::class)]
#[CoversClass(RenameToPattern::class)]
#[CoversClass(MoveToFolders::class)]
#[CoversClass(MigrateTypeFolders::class)]
#[CoversClass(EntryIds::class)]
#[CoversClass(EntryIdReport::class)]
final class ContentCommandsTest extends TestCase
{
	use BuildsContentSite;

	private function tester(): CommandTester
	{
		return new CommandTester($this->site()->container()->make(Console::class));
	}

	public function testIndexesContent(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$first = $tester->run('content:index');

		$this->assertTrue($first->isSuccessful());
		$this->assertMatchesRegularExpression('/^Indexed 19 entries, 19 added, 0 changed, 0 removed, \d+ ms$/m', $first->output);

		$again = $tester->run('content:index -v');

		$this->assertStringContainsString('0 added, 0 changed, 0 removed', $again->output);
		$this->assertStringContainsString('The index was already up to date.', $again->output);

		$this->entry('_post/new.md', 'title: New');

		$this->assertStringContainsString('added  _post/new.md', $tester->run('content:index -v')->output);
		$this->assertStringContainsString('0 added, 0 changed, 0 removed', $tester->run('content:index --full')->output);

		$this->entry('broken.md', "title: [unclosed\n");

		$broken = $tester->run('content:index');

		$this->assertSame(ExitCode::Failure, $broken->exitCode);
		$this->assertStringContainsString('broken.md: Invalid front matter', $broken->errors);
		$this->assertStringContainsString('1 file not indexed', $broken->errors);
	}

	public function testLintsContent(): void
	{
		$this->standardContent();

		// The standard posts' folder entry is an error (D-514); a clean
		// site has it as a file.
		rename($this->temporaryDirectory() . '/user/content/_post/hello/index.md', $this->temporaryDirectory() . '/user/content/_post/2010-01-01.hello.md');
		$tester = $this->tester();

		$clean = $tester->run('content:lint');

		$this->assertTrue($clean->isSuccessful());
		$this->assertSame("Checked 19 files, no problems\n", $clean->output);

		$strict = $tester->run('content:lint --strict');

		$this->assertTrue($strict->isSuccessful());
		$this->assertMatchesRegularExpression('#_post/2008-04-05\.spring\.md\n  notice\s+author\s+is read as "authors"\.\n  notice\s+tag\s+is not declared by the schema\.\n#', $strict->output);
		$this->assertMatchesRegularExpression('/Checked 19 files, \d+ notices/', $strict->output);

		$this->entry('about.md', "title: Old\npublished: soon");

		$failed = $tester->run('content:lint');

		$this->assertSame(ExitCode::Failure, $failed->exitCode);
		$this->assertMatchesRegularExpression('#about\.md\n  error\s+published\s+must be a date#', $failed->output);
		$this->assertMatchesRegularExpression('#  warning\s+file\s+is the same entry as about/index\.md, which wins\.#', $failed->output);
		$this->assertStringContainsString('Checked 20 files, 1 error, 1 warning', $failed->errors);
	}

	public function testListsContent(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$all = $tester->run('content:list');

		$this->assertTrue($all->isSuccessful());
		$this->assertStringContainsString('19 entries.', $all->output);
		$this->assertMatchesRegularExpression('/post\s+\(landing\)\s+Blog\s+published\s+public/', $all->output);
		$this->assertMatchesRegularExpression('/page\s+about\/biography\s+Biography\s+/', $all->output);

		$drafts = $tester->run('content:list --type=post --status=draft');

		$this->assertMatchesRegularExpression('/post\s+unfinished\s+Unfinished\s+draft\s+public\s+2020-01-01 00:00\s+/', $drafts->output);
		$this->assertStringContainsString('1 entry.', $drafts->output);
		$this->assertStringContainsString('No entries found.', $tester->run('content:list --type=category --status=scheduled')->output);

		$unknown = $tester->run('content:list --type=movie');

		$this->assertSame(ExitCode::Invalid, $unknown->exitCode);
		$this->assertStringContainsString('There is no "movie" content type; the types are page, profile, category, post.', $unknown->errors);
	}

	public function testChecksAndFixesIds(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/content/none.md', "---\ntitle: None\n---\n");
		$this->writeTemporaryFile('user/content/copy.md', "---\ntitle: Copy\nid: " . self::idFor('about/biography.md') . "\n---\n");
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$check = $tester->run('content:ids');

		$this->assertSame(ExitCode::Failure, $check->exitCode);
		$this->assertStringContainsString('missing  none.md', $check->output);
		$this->assertMatchesRegularExpression('#shared   ' . self::idFor('about/biography.md') . '\n +about/biography\.md\n +copy\.md#', $check->output, 'The files sharing it, below.');
		$this->assertStringContainsString("1 file is missing a valid id. 1 id is shared.\n  Add the missing ones with blush content:ids --write. Keep each shared id on one file with blush content:ids --keep={path}.", $check->errors);
		$this->assertStringNotContainsString('id:', (string) file_get_contents("{$content}/none.md"), 'Checking changes nothing.');

		$this->assertSame(ExitCode::Failure, $tester->run('content:ids --keep=none.md')->exitCode, 'Only a file that shares its id can keep it.');

		$fixed = $tester->run('content:ids --write --keep=about/biography.md');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertMatchesRegularExpression('/^  ok\s+added  copy\.md \([0-9a-f-]{36}\)$/m', $fixed->output);
		$this->assertMatchesRegularExpression('/^  ok\s+added  none\.md \([0-9a-f-]{36}\)$/m', $fixed->output);
		$this->assertStringContainsString('Every content file has an id of its own.', $fixed->output);
		$this->assertStringContainsString('id: ' . self::idFor('about/biography.md'), (string) file_get_contents("{$content}/about/biography.md"), 'The file kept keeps its id.');
		$this->assertTrue($tester->run('content:ids')->isSuccessful());
	}

	public function testRenamesFilesToTheirTypesPattern(): void
	{
		$this->standardContent();
		$post = json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/types/post.json'), true, flags: JSON_THROW_ON_ERROR);
		$this->writeTemporaryFile('user/data/types/post.json', json_encode([...(array) $post, 'filename' => '{slug}'], JSON_THROW_ON_ERROR));
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$check = $tester->run('content:filenames --type=post');

		$this->assertTrue($check->isSuccessful(), 'Older names keep working, so a list doesn\'t fail.');
		$this->assertStringContainsString('rename   _post/2008-04-05.spring.md → _post/spring.md', $check->output);
		$this->assertStringContainsString("5 entries are named by another pattern\n\nRename them with blush content:filenames --write.", $check->output);
		$this->assertFileExists("{$content}/_post/2008-04-05.spring.md", 'Checking changes nothing.');
		$this->assertSame(ExitCode::Invalid, $tester->run('content:filenames --type=movie')->exitCode);

		$fixed = $tester->run('content:filenames --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('renamed  _post/2008-04-05.spring.md → _post/spring.md', $fixed->output);
		$this->assertStringContainsString('No entries need renaming', $fixed->output);
		$this->assertFileExists("{$content}/_post/spring.md");
	}

	public function testMovesEntriesToTheirFolders(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$check = $tester->run('content:folders');

		$this->assertSame(ExitCode::Failure, $check->exitCode, 'They\'re lint errors (D-514).');
		$this->assertStringContainsString('move  _post/hello/index.md → _post/hello.md', $check->output);
		$this->assertStringContainsString("1 entry isn't in its type's folders.\n  Move it with blush content:folders --write.", $check->errors);

		$fixed = $tester->run('content:folders --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('moved  _post/hello/index.md → _post/hello.md', $fixed->output);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_post/hello.md');
	}

	public function testMovesDataTypesThatNameTheirFolder(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->entry('recipes/soup.md', 'title: Soup');
		$tester = $this->tester();

		$check = $tester->run('content:type-folders');

		$this->assertSame(ExitCode::Failure, $check->exitCode);
		$this->assertStringContainsString('folder  recipe: recipes/ to _recipe/', $check->output);
		$this->assertStringContainsString("1 data type names its folder.\n  Move it with blush content:type-folders --write.", $check->errors);

		$fixed = $tester->run('content:type-folders --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('moved  recipe (1 file moved)', $fixed->output);
		$this->assertStringContainsString('No data type names its folder.', $fixed->output);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_recipe/soup.md');
		$this->assertSame(['urls' => ['prefix' => 'recipes']], array_diff_key((array) json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/types/recipe.json'), true), ['id' => true]), 'Its addresses stay (D-683).');
	}

	public function testWritesMissingTerms(): void
	{
		$this->standardContent();
		$this->entry('_post/2009-01-01.dangling.md', "title: Dangling\npublished: 2009-01-01\ncategory: Lost Cause");
		$tester = $this->tester();

		$check = $tester->run('content:terms');

		$this->assertSame(ExitCode::Failure, $check->exitCode, 'They\'re lint errors (D-584).');
		$this->assertStringContainsString('missing  category/lost-cause (Lost Cause)', $check->output);
		$this->assertStringContainsString("1 term or profile has no file, so the site leaves it out.\n  Write it with blush content:terms --write.", $check->errors);

		$fixed = $tester->run('content:terms --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('created  _category/lost-cause.md', $fixed->output);
		$this->assertStringContainsString('Every term and profile entries name has a file.', $fixed->output);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_category/lost-cause.md');
	}

	public function testAsksForWhatsNotGiven(): void
	{
		$this->standardContent();
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$studio = $tester->run(['content:new', 'page', 'Studio'], ['', '', '']);

		$this->assertTrue($studio->isSuccessful(), $studio->errors);

		$asked = $tester->run(['content:new'], ['page', 'The Team', 'Not A Slug', 'team', 'studio', '2']);

		$this->assertTrue($asked->isSuccessful(), $asked->errors);
		$this->assertStringContainsString('is not a slug', $asked->errors, 'Asked again until it is one.');
		$this->assertFileExists("{$content}/studio/team.md", 'Under the page picked by its path (D-721).');
		$this->assertStringContainsString('status: draft', (string) file_get_contents("{$content}/studio/team.md"));

		$flags = $tester->run(['content:new', 'page', 'History', '--parent=studio', '-n']);

		$this->assertTrue($flags->isSuccessful(), $flags->errors);
		$this->assertFileExists("{$content}/studio/history.md");
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'post', 'X', '--parent=studio', '-n'])->exitCode, 'Only a tree\'s pages have parents.');
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'page', 'X', '--parent=nowhere', '-n'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', '-n'])->exitCode, 'The type can\'t be defaulted.');
	}

	public function testCreatesEntries(): void
	{
		$this->standardContent();
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$post = $tester->run(['content:new', 'post', 'Hello, World: Again!']);

		$this->assertTrue($post->isSuccessful());
		$this->assertMatchesRegularExpression('#\ACreated user/content/_post/hello-world-again\.md\n\nEdit it in the admin: http://localhost/admin/content/post/[0-9a-f-]{36}\n\z#', $post->output, 'With where the admin edits it (D-721).');
		$this->assertMatchesRegularExpression(
			'/\A---\ntitle: "Hello, World: Again!"\npublished: 2026-06-01 12:00:00 -05:00\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\n\z/',
			(string) file_get_contents("{$content}/_post/hello-world-again.md"),
			'With an id, last (D-477).'
		);
		$this->assertStringContainsString('hello-world-again', $tester->run('content:list --type=post')->output);

		$tester->run(['content:new', 'page', 'Colophon', '--slug=credits', '--draft']);

		$this->assertMatchesRegularExpression('/\A---\ntitle: Colophon\npublished: 2026-06-01 12:00:00 -05:00\nstatus: draft\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\n\z/', (string) file_get_contents("{$content}/credits.md"));

		$tester->run(['content:new', 'category', 'Life']);

		$this->assertFileExists("{$content}/_category/life.md");

		$again = $tester->run(['content:new', 'category', 'Life']);

		$this->assertSame(ExitCode::Failure, $again->exitCode);
		$this->assertStringContainsString('user/content/_category/life.md already exists.', $again->errors);
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'movie', 'Alien'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'page', '!!!'])->exitCode);
		$this->assertStringContainsString('"Not A Slug" is not a slug; try "not-a-slug".', $tester->run(['content:new', 'page', 'X', '--slug=Not A Slug'])->errors);
	}

	public function testLogsWhatItCreatesAsTheCommandLineOrAnAccount(): void
	{
		$this->standardContent();
		$app    = $this->site();
		$tester = new CommandTester($app->container()->make(Console::class));
		$log    = $app->container()->make(ActivityLog::class);

		$accounts = $app->container()->make(Accounts::class);
		$accounts->setProfile($accounts->create('jane', 'a long enough password', ['author'], email: 'jane@example.test'), self::idFor('_profile/justintadlock.md'));
		$accounts->create('sam', 'a long enough password', ['contributor'], email: 'sam@example.test');

		$this->assertTrue($tester->run(['content:new', 'post', 'From the Shell', '-n'])->isSuccessful());

		[$changes] = $log->list(new ActivityFilter(via: Via::Cli));

		$this->assertSame(['Created “From the Shell”', ''], [isset($changes[0]) ? (string) $changes[0]->summary : null, $changes[0]->account ?? null], 'Logged as the command line, with no account (D-758).');

		$this->clock->advance('PT1M');

		$janes = $tester->run(['content:new', 'post', 'From Jane', '--account=jane', '-n']);

		$this->assertTrue($janes->isSuccessful(), $janes->errors);
		$this->assertSame('From Jane', $log->list(new ActivityFilter(via: Via::Cli))[0][0]->title ?? null);
		$this->assertStringContainsString('authors: [justintadlock]', (string) file_get_contents($this->temporaryDirectory() . '/user/content/_post/from-jane.md'), 'With the account\'s byline.');

		$sams = $tester->run(['content:new', 'post', 'From Sam', '--account=sam', '-n']);

		$this->assertSame(ExitCode::Failure, $sams->exitCode, 'With the account\'s permissions: a contributor can\'t publish.');
		$this->assertStringContainsString('must be drafts', $sams->errors);
		$this->assertTrue($tester->run(['content:new', 'post', 'From Sam', '--account=sam', '--draft', '-n'])->isSuccessful());
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'post', 'X', '--account=nobody', '-n'])->exitCode);
	}
}
