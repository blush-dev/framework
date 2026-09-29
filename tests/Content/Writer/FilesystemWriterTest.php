<?php

/**
 * Content writer tests.
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
use Blush\Cache\ContentVersion;
use Blush\Content\ContentRepository;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Content\Writer\WriteResult;
use Blush\Core\Application;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(FilesystemWriter::class)]
#[CoversClass(DocumentEditor::class)]
#[CoversClass(EntryChanges::class)]
#[CoversClass(EditableEntry::class)]
#[CoversClass(WriteResult::class)]
final class FilesystemWriterTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A jtcom post: aligned keys, the 1.x `date`, empty values, a blank
	 * line after the front matter.
	 */
	private const string POST = "---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\nformat    :\ncategory  : [life]\n---\n\nThe body.\n";

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/content/_posts/2022-03-29.rekindling-the-flame.md', self::POST);
		$this->app = $this->site('development');
	}

	private function writer(): ContentWriter
	{
		return $this->app->container()->make(ContentWriter::class);
	}

	private function content(): ContentRepository
	{
		return $this->app->container()->make(ContentRepository::class);
	}

	private function file(string $id): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/user/content/{$id}");
	}

	public function testLoadsAFileAsWritten(): void
	{
		$entry = $this->writer()->load('_posts/2022-03-29.rekindling-the-flame.md');

		$this->assertSame('justintadlock', $entry->frontMatter['author'] ?? null);
		$this->assertArrayHasKey('date', $entry->frontMatter);
		$this->assertSame("\nThe body.\n", $entry->body);
		$this->assertSame(hash('sha256', self::POST), $entry->revision);
	}

	public function testUpdatesOnlyWhatChanged(): void
	{
		$id      = '_posts/2022-03-29.rekindling-the-flame.md';
		$version = $this->app->container()->make(ContentVersion::class)->current();

		$result = $this->writer()->update($id, new EntryChanges(set: ['status' => 'draft', 'published' => '2022-04-01 09:00:00 -05:00']), $this->writer()->load($id)->revision);

		$this->assertSame(
			"---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-04-01 09:00:00 -05:00\nformat    :\ncategory  : [life]\nstatus: draft\n---\n\nThe body.\n",
			$this->file($id)
		);
		$this->assertSame(hash('sha256', $this->file($id)), $result->revision);
		$this->assertContains($id, $result->index->changed);
		$this->assertNotSame($version, $this->app->container()->make(ContentVersion::class)->current());
		$this->assertSame(Status::Draft, $this->content()->find($id)?->status);
	}

	public function testReplacesTheBodyAndRemovesKeys(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->update($id, new EntryChanges(remove: ['format'], body: "\nNew words.\n"));

		$this->assertSame("---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\ncategory  : [life]\n---\n\nNew words.\n", $this->file($id));
	}

	public function testKeepsAFileThatEndsAtItsFrontMatter(): void
	{
		$this->writeTemporaryFile('user/content/topics/age.md', "---\ntitle : Age\n---");
		$this->app = $this->site('development');

		$this->writer()->update('topics/age.md', new EntryChanges(set: ['title' => 'Ages']));
		$this->assertSame("---\ntitle : Ages\n---", $this->file('topics/age.md'));

		$this->writer()->update('topics/age.md', new EntryChanges(body: "About ages.\n"));
		$this->assertSame("---\ntitle : Ages\n---\nAbout ages.\n", $this->file('topics/age.md'));
	}

	public function testRefusesToOverwriteAChangeMadeMeanwhile(): void
	{
		$id    = '_posts/2022-03-29.rekindling-the-flame.md';
		$stale = $this->writer()->load($id)->revision;

		$this->writer()->update($id, new EntryChanges(set: ['title' => 'Someone else was here']));

		try {
			$this->writer()->update($id, new EntryChanges(set: ['title' => 'Mine']), $stale);
			$this->fail('Overwrote a newer change.');
		} catch (WriteConflict) {
			$this->assertStringContainsString('Someone else was here', $this->file($id));
		}
	}

	public function testRefusesEditsItCantMakeSafely(): void
	{
		$this->writeTemporaryFile('user/content/odd.md', "---\ntags: [one,\ntwo]\ntitle: Odd\n---\n");
		$this->app = $this->site('development');

		try {
			$this->writer()->update('odd.md', new EntryChanges(set: ['tags' => ['three']]));
			$this->fail('Wrote a file it couldn\'t edit safely.');
		} catch (WriteException $e) {
			$this->assertStringContainsString('nothing was saved', $e->getMessage() . ' nothing was saved');
			$this->assertSame("---\ntags: [one,\ntwo]\ntitle: Odd\n---\n", $this->file('odd.md'));
		}
	}

	public function testCreatesEntries(): void
	{
		$post = $this->writer()->create(
			$this->app->container()->make(ContentTypes::class)->get('post'),
			'fresh-start',
			new EntryChanges(set: ['title' => 'A Fresh Start', 'status' => 'draft'], body: "\nHello.\n")
		);

		$this->assertSame('_posts/2026-06-01.fresh-start.md', $post->id);
		$this->assertSame("---\ntitle: \"A Fresh Start\"\nstatus: draft\n---\n\nHello.\n", $this->file($post->id));
		$this->assertSame('A Fresh Start', $this->content()->find($post->id)?->title);

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('already exists');

		$this->writer()->create($this->app->container()->make(ContentTypes::class)->get('post'), 'fresh-start', new EntryChanges(set: ['title' => 'Again']));
	}

	public function testEditsJsonAndYamlEntries(): void
	{
		$this->writeTemporaryFile('user/content/data.yaml', "# Kept.\ntitle: Data\nbody: Old\n");
		$this->app = $this->site('development');

		$this->writer()->update('notes.json', new EntryChanges(set: ['title' => 'Better Notes'], body: 'New *notes*.'));
		$this->writer()->update('data.yaml', new EntryChanges(set: ['status' => 'draft'], body: "Line one\nLine two"));

		$this->assertSame("{\n    \"title\": \"Better Notes\",\n    \"body\": \"New *notes*.\"\n}\n", $this->file('notes.json'));
		$this->assertSame("# Kept.\ntitle: Data\nbody: |-\n  Line one\n  Line two\nstatus: draft\n", $this->file('data.yaml'));
	}

	public function testRenamesKeepingTheDateOrTheBundle(): void
	{
		$renamed = $this->writer()->rename('_posts/2022-03-29.rekindling-the-flame.md', 'the-flame');

		$this->assertSame('_posts/2022-03-29.the-flame.md', $renamed->id);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/2022-03-29.the-flame.md');
		$this->assertNull($this->content()->find('_posts/2022-03-29.rekindling-the-flame.md'));

		$bundle = $this->writer()->rename('_posts/hello/index.md', 'greetings');

		$this->assertSame('_posts/greetings/index.md', $bundle->id);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/greetings/photo.jpg', 'A bundle\'s media moves with it.');

		$this->expectException(WriteException::class);

		$this->writer()->rename('_posts/index.md', 'blog');
	}

	public function testDeletingMovesToTheTrash(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->delete($id);

		$this->assertFileDoesNotExist($this->temporaryDirectory() . "/user/content/{$id}");
		$this->assertFileExists($this->temporaryDirectory() . "/storage/trash/20260601-120000/user/content/{$id}");
		$this->assertNull($this->content()->find($id));
	}

	public function testStaysInsideTheContentFolder(): void
	{
		$cases = ['../config/app.php', '../../escape.md', 'shell.php', '/etc/passwd.md', ''];

		foreach ($cases as $id) {
			try {
				$this->writer()->update($id, new EntryChanges(set: ['title' => 'x']));
				$this->fail("Wrote {$id}.");
			} catch (WriteException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testRefusesValuesThatArentData(): void
	{
		$this->expectException(WriteException::class);

		new EntryChanges(set: ['title' => new \stdClass()]);
	}
}
