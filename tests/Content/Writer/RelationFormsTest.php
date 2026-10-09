<?php

/**
 * Relation forms tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\FileRefs;
use Blush\Console\Console;
use Blush\Content\EntryRefs;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\RelationForms;
use Blush\Core\Application;
use Blush\Console\Testing\CommandTester;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(RelationForms::class)]
#[CoversClass(FilesystemWriter::class)]
#[CoversClass(EntryRefs::class)]
#[CoversClass(FileRefs::class)]
final class RelationFormsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->contentConfig([
			'types'     => [
				'post'     => ['path' => '_posts'],
				'category' => ['path' => 'topics', 'order' => 'position', 'hierarchical' => true]
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true],
				'see_also' => ['kind' => 'reference', 'from' => ['post'], 'to' => ['page']]
			]
		]);

		$this->entry('index.md', 'title: Home');
		$this->entry('contact.md', 'title: Contact');
		$this->entry('about/index.md', 'title: About');
		$this->entry('about/team.md', 'title: Team');
		$this->entry('topics/art.md', 'title: Art');
		$this->entry('topics/painting.md', "title: Painting\nparent: art");
		$this->entry('_posts/a.md', "title: A\npublished: 2026-01-01\ncategory: [Painting, Book Reviews]");
		$this->entry('_posts/b.md', "title: B\npublished: 2026-01-02\ncategory: painting\nsee_also: about/team");
		$this->entry('_posts/c.md', "title: C\npublished: 2026-01-03\ncategory: [" . self::idFor('topics/art.md') . ']');

		$this->app = $this->site('development');
	}

	private function writer(): FilesystemWriter
	{
		return $this->app->container()->make(FilesystemWriter::class);
	}

	private function file(string $path): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/user/content/{$path}");
	}

	public function testASaveFilesBothForms(): void
	{
		$result = $this->writer()->update('_posts/a.md', new EntryChanges(set: ['title' => 'A, Again']));

		$this->assertSame(
			"---\nid: " . self::idFor('_posts/a.md') . "\ntitle: \"A, Again\"\npublished: 2026-01-01\ncategory: [painting, 'Book Reviews']\nrefs:\n  category:\n    painting: " . self::idFor('topics/painting.md') . "\n---\n",
			$this->file('_posts/a.md'),
			'The slug Blush writes, a value naming nothing kept as typed, and the ids of what links (D-596).'
		);
		$this->assertSame(RecordBuilder::hash($this->file('_posts/a.md')), $result->version, 'The revision is the file as filed.');

		$this->writer()->update('_posts/c.md', new EntryChanges(set: ['title' => 'C, Again']));

		$this->assertStringContainsString("category: [art]\nrefs:\n  category:\n    art: " . self::idFor('topics/art.md') . "\n", $this->file('_posts/c.md'), 'An id written as a value is written as its slug.');

		$this->writer()->update('topics/painting.md', new EntryChanges(set: ['title' => 'Paintings']));

		$this->assertStringContainsString("parent: art\nrefs:\n  parent:\n    art: " . self::idFor('topics/art.md') . "\n", $this->file('topics/painting.md'), 'A parent is filed as a ref (D-591).');
	}

	public function testANewEntryIsFiledToo(): void
	{
		$type   = $this->app->container()->make(ContentTypes::class)->find('post');
		$result = $this->writer()->create($type ?? self::fail('No post type.'), 'd', new EntryChanges(set: ['title' => 'D', 'category' => 'art']));

		$this->assertStringContainsString("category: art\npublished: ", $this->file($result->path), 'One value written on its own stays on its own.');
		$this->assertStringContainsString("refs:\n  category:\n    art: " . self::idFor('topics/art.md') . "\nid: ", $this->file($result->path), 'Before the id, which stays last.');
	}

	public function testReferrersFollowARename(): void
	{
		$this->writer()->rename('topics/painting.md', 'paintings');

		$this->assertStringContainsString("category: paintings\nsee_also: about/team\nrefs:\n  category:\n    paintings: " . self::idFor('topics/painting.md') . "\n  see_also:\n    about/team: " . self::idFor('about/team.md') . "\n", $this->file('_posts/b.md'), 'Filed before, rewritten after (D-596), at the end since the id isn\'t last.');
		$this->assertStringContainsString("category: [paintings, 'Book Reviews']", $this->file('_posts/a.md'));
		$this->assertStringContainsString('category: [' . self::idFor('topics/art.md') . ']', $this->file('_posts/c.md'), 'An entry not linking to it is left alone.');
	}

	public function testReferrersFollowAMove(): void
	{
		$this->writer()->move('about/team.md', 'contact.md');

		$this->assertStringContainsString("see_also: contact/team\nrefs:\n", $this->file('_posts/b.md'), 'A page\'s path follows it in a tree.');
		$this->assertStringContainsString("  see_also:\n    contact/team: " . self::idFor('about/team.md') . "\n", $this->file('_posts/b.md'));
	}

	public function testTheFixingToolFilesWhatOtherWaysWrote(): void
	{
		$refs = $this->app->container()->make(EntryRefs::class);

		$this->assertSame(['_posts/a.md' => ['category'], '_posts/b.md' => ['category', 'see_also'], '_posts/c.md' => ['category'], 'about/team.md' => ['parent'], 'topics/painting.md' => ['parent']], $refs->report(), 'A tree page\'s parent id is filed from its folder.');

		$tester = new CommandTester($this->app->container()->make(Console::class));
		$check  = $tester->run('content:refs');

		$this->assertFalse($check->isSuccessful());
		$this->assertStringContainsString('unfiled  _posts/b.md (category, see_also)', $check->output);
		$this->assertStringContainsString('5 files have links not filed with their ids; file them with --write.', $check->errors);

		$fixed = $tester->run('content:refs --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('filed    _posts/c.md', $fixed->output);
		$this->assertStringContainsString('Every link between entries is filed with its id.', $fixed->output);
		$this->assertSame([], $refs->report());
		$this->assertStringContainsString("category: [art]\n", $this->file('_posts/c.md'));
	}
}
