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
use Blush\Console\Commands\CreateContent;
use Blush\Console\Commands\FixIds;
use Blush\Console\Commands\FlattenCollections;
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
#[CoversClass(FlattenCollections::class)]
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
		$this->assertMatchesRegularExpression('/^Indexed 19 entries \(19 added, 0 changed, 0 removed\) in \d+ ms\.$/m', $first->output);

		$again = $tester->run('content:index -v');

		$this->assertStringContainsString('(0 added, 0 changed, 0 removed)', $again->output);
		$this->assertStringContainsString('The index was already up to date.', $again->output);

		$this->entry('_posts/new.md', 'title: New');

		$this->assertStringContainsString('Added _posts/new.md', $tester->run('content:index -v')->output);
		$this->assertStringContainsString('(0 added, 0 changed, 0 removed)', $tester->run('content:index --full')->output);

		$this->entry('broken.md', "title: [unclosed\n");

		$broken = $tester->run('content:index');

		$this->assertSame(ExitCode::Failure, $broken->exitCode);
		$this->assertStringContainsString('broken.md: Invalid front matter', $broken->errors);
		$this->assertStringContainsString('1 file(s) could not be indexed.', $broken->errors);
	}

	public function testLintsContent(): void
	{
		$this->standardContent();

		// The standard posts' folder entry is an error (D-514); a clean
		// site has it as a file.
		rename($this->temporaryDirectory() . '/user/content/_posts/hello/index.md', $this->temporaryDirectory() . '/user/content/_posts/2010-01-01.hello.md');
		$tester = $this->tester();

		$clean = $tester->run('content:lint');

		$this->assertTrue($clean->isSuccessful());
		$this->assertSame("Checked 19 files: 0 errors, 0 warnings.\n", $clean->output);

		$strict = $tester->run('content:lint --strict');

		$this->assertTrue($strict->isSuccessful());
		$this->assertStringContainsString("_posts/2008-04-05.spring.md\n  notice  author: is read as \"authors\".\n  notice  tag: is not declared by the schema.\n", $strict->output);
		$this->assertMatchesRegularExpression('/Checked 19 files: 0 errors, 0 warnings, \d+ notices\./', $strict->output);

		$this->entry('about.md', "title: Old\npublished: soon");

		$failed = $tester->run('content:lint');

		$this->assertSame(ExitCode::Failure, $failed->exitCode);
		$this->assertStringContainsString("about.md\n  error   published: must be a date", $failed->output);
		$this->assertStringContainsString('  warning file: is the same entry as about/index.md, which wins.', $failed->output);
		$this->assertStringContainsString('Checked 20 files: 1 error, 1 warning.', $failed->errors);
	}

	public function testListsContent(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$all = $tester->run('content:list');

		$this->assertTrue($all->isSuccessful());
		$this->assertStringContainsString('19 entries.', $all->output);
		$this->assertMatchesRegularExpression('/\| post +\| \(landing\) +\| Blog +\| published +\| public/', $all->output);
		$this->assertMatchesRegularExpression('/\| page +\| about\/biography +\| Biography +\|/', $all->output);

		$drafts = $tester->run('content:list --type=post --status=draft');

		$this->assertMatchesRegularExpression('/\| post +\| unfinished +\| Unfinished +\| draft +\| public +\| 2020-01-01 00:00 \|/', $drafts->output);
		$this->assertStringContainsString('1 entry.', $drafts->output);
		$this->assertStringContainsString('No entries found.', $tester->run('content:list --type=category --status=scheduled')->output);

		$unknown = $tester->run('content:list --type=movie');

		$this->assertSame(ExitCode::Invalid, $unknown->exitCode);
		$this->assertStringContainsString('There is no "movie" content type; the types are page, profile, post, category.', $unknown->errors);
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
		$this->assertStringContainsString('shared   ' . self::idFor('about/biography.md') . "\n         about/biography.md\n         copy.md", $check->output);
		$this->assertStringContainsString('1 file is missing a valid id; add them with --write. 1 id is shared; keep each on one file with --keep={path}.', $check->errors);
		$this->assertStringNotContainsString('id:', (string) file_get_contents("{$content}/none.md"), 'Checking changes nothing.');

		$this->assertSame(ExitCode::Failure, $tester->run('content:ids --keep=none.md')->exitCode, 'Only a file that shares its id can keep it.');

		$fixed = $tester->run('content:ids --write --keep=about/biography.md');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertMatchesRegularExpression('/^added    [0-9a-f-]{36}  copy\.md$/m', $fixed->output);
		$this->assertMatchesRegularExpression('/^added    [0-9a-f-]{36}  none\.md$/m', $fixed->output);
		$this->assertStringContainsString('Every content file has an id of its own.', $fixed->output);
		$this->assertStringContainsString('id: ' . self::idFor('about/biography.md'), (string) file_get_contents("{$content}/about/biography.md"), 'The file kept keeps its id.');
		$this->assertTrue($tester->run('content:ids')->isSuccessful());
	}

	public function testRenamesFilesToTheirTypesPattern(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/types/post.yaml', "filename: \"{slug}\"\n");
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$check = $tester->run('content:filenames --type=post');

		$this->assertTrue($check->isSuccessful(), 'Older names keep working, so a list doesn\'t fail.');
		$this->assertStringContainsString('rename   _posts/2008-04-05.spring.md → _posts/spring.md', $check->output);
		$this->assertStringContainsString('5 entries are named by another pattern; rename them with --write.', $check->output);
		$this->assertFileExists("{$content}/_posts/2008-04-05.spring.md", 'Checking changes nothing.');
		$this->assertSame(ExitCode::Invalid, $tester->run('content:filenames --type=movie')->exitCode);

		$fixed = $tester->run('content:filenames --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('renamed  _posts/2008-04-05.spring.md → _posts/spring.md', $fixed->output);
		$this->assertStringContainsString('No entries need renaming', $fixed->output);
		$this->assertFileExists("{$content}/_posts/spring.md");
	}

	public function testFlattensCollections(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$check = $tester->run('content:flatten');

		$this->assertSame(ExitCode::Failure, $check->exitCode, 'They\'re lint errors (D-514).');
		$this->assertStringContainsString('move     _posts/hello/index.md → _posts/hello.md', $check->output);
		$this->assertStringContainsString('1 entry is kept in a folder; move it with --write.', $check->errors);

		$fixed = $tester->run('content:flatten --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('moved    _posts/hello/index.md → _posts/hello.md', $fixed->output);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello.md');
	}

	public function testWritesMissingTerms(): void
	{
		$this->standardContent();
		$this->entry('_posts/2009-01-01.dangling.md', "title: Dangling\npublished: 2009-01-01\ncategory: Lost Cause");
		$tester = $this->tester();

		$check = $tester->run('content:terms');

		$this->assertSame(ExitCode::Failure, $check->exitCode, 'They\'re lint errors (D-584).');
		$this->assertStringContainsString('missing  category/lost-cause (Lost Cause)', $check->output);
		$this->assertStringContainsString('1 term or profile has no file, so the site leaves it out; write it with --write.', $check->errors);

		$fixed = $tester->run('content:terms --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('created  topics/lost-cause.md', $fixed->output);
		$this->assertStringContainsString('Every term and profile entries name has a file.', $fixed->output);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/topics/lost-cause.md');
	}

	public function testCreatesEntries(): void
	{
		$this->standardContent();
		$tester  = $this->tester();
		$content = $this->temporaryDirectory() . '/user/content';

		$post = $tester->run(['content:new', 'post', 'Hello, World: Again!']);

		$this->assertTrue($post->isSuccessful());
		$this->assertSame("Created user/content/_posts/hello-world-again.md\n", $post->output);
		$this->assertMatchesRegularExpression(
			'/\A---\ntitle: "Hello, World: Again!"\npublished: 2026-06-01 12:00:00 -05:00\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\n\z/',
			(string) file_get_contents("{$content}/_posts/hello-world-again.md"),
			'With an id, last (D-477).'
		);
		$this->assertStringContainsString('hello-world-again', $tester->run('content:list --type=post')->output);

		$tester->run(['content:new', 'page', 'Colophon', '--slug=credits', '--draft']);

		$this->assertMatchesRegularExpression('/\A---\ntitle: Colophon\npublished: 2026-06-01 12:00:00 -05:00\nstatus: draft\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\n\z/', (string) file_get_contents("{$content}/credits.md"));

		$tester->run(['content:new', 'category', 'Life']);

		$this->assertFileExists("{$content}/topics/life.md");

		$again = $tester->run(['content:new', 'category', 'Life']);

		$this->assertSame(ExitCode::Failure, $again->exitCode);
		$this->assertStringContainsString('user/content/topics/life.md already exists.', $again->errors);
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'movie', 'Alien'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $tester->run(['content:new', 'page', '!!!'])->exitCode);
		$this->assertStringContainsString('"Not A Slug" is not a slug; try "not-a-slug".', $tester->run(['content:new', 'page', 'X', '--slug=Not A Slug'])->errors);
	}
}
