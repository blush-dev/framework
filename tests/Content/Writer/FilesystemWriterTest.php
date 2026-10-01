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
use Blush\Content\Writer\TrashedEntry;
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
#[CoversClass(TrashedEntry::class)]
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
		$this->assertSame(filemtime($this->temporaryDirectory() . '/user/content/_posts/2022-03-29.rekindling-the-flame.md'), $entry->modified);
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

	public function testKeepsTheBlankLinesBeforeTheBody(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->update($id, new EntryChanges(body: "New words.\n"));
		$this->assertStringEndsWith("---\n\nNew words.\n", $this->file($id));

		$this->writer()->update($id, new EntryChanges(body: "\n\nSpaced.\n"));
		$this->assertStringEndsWith("---\n\n\nSpaced.\n", $this->file($id), 'A body that starts with blank lines is written as given.');

		$this->writeTemporaryFile('user/content/topics/age.md', "No front matter.\n");
		$this->app = $this->site('development');

		$this->writer()->update('topics/age.md', new EntryChanges(set: ['title' => 'Age'], body: "About ages.\n"));
		$this->assertSame("---\ntitle: Age\n---\n\nAbout ages.\n", $this->file('topics/age.md'));
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

	public function testDuplicatesBesideTheEntryUnderAFreeName(): void
	{
		$id      = '_posts/2022-03-29.rekindling-the-flame.md';
		$changes = new EntryChanges(set: ['title' => 'The Copy', 'status' => 'draft']);

		$first  = $this->writer()->duplicate($id, 'the-copy', $changes);
		$second = $this->writer()->duplicate($id, 'the-copy', $changes);

		$this->assertSame('_posts/2026-06-01.the-copy.md', $first->id, 'A dated copy takes today\'s date.');
		$this->assertSame('_posts/2026-06-01.the-copy-2.md', $second->id, 'A taken name gets a number.');
		$this->assertSame("---\ntitle     : \"The Copy\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\nformat    :\ncategory  : [life]\nstatus: draft\n---\n\nThe body.\n", $this->file($first->id));
		$this->assertSame(self::POST, $this->file($id), 'The original is untouched.');
		$this->assertSame('The Copy', $this->content()->find($first->id)?->title);

		$bundle = $this->writer()->duplicate('_posts/hello/index.md', 'hello-copy', new EntryChanges(set: ['title' => 'Hello (Copy)']));

		$this->assertSame('_posts/hello-copy/index.md', $bundle->id);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello-copy/photo.jpg', 'A bundle\'s media is copied with it.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg');

		$this->expectException(WriteException::class);

		$this->writer()->duplicate('_posts/index.md', 'blog', $changes);
	}

	public function testDeletingMovesToTheTrash(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->delete($id);

		$folders = glob($this->temporaryDirectory() . '/storage/trash/20260601-120000-*') ?: [];

		$this->assertFileDoesNotExist($this->temporaryDirectory() . "/user/content/{$id}");
		$this->assertCount(1, $folders, 'Each trashed entry gets its own folder.');
		$this->assertFileExists(($folders[0] ?? '') . "/user/content/{$id}");
		$this->assertSame(['entry' => $id, 'bundle' => false, 'trashed' => '2026-06-01T12:00:00-05:00'], json_decode((string) file_get_contents(($folders[0] ?? '') . '/trash.json'), true));
		$this->assertNull($this->content()->find($id));
	}

	public function testListsTheTrash(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');
		$this->writer()->delete('_posts/hello/index.md');

		// Trash from before manifests is listed file by file.
		$this->writeTemporaryFile('storage/trash/20250101-090000/user/content/old.md', "---\ntitle: Old\n---\n");

		$trashed = $this->writer()->trashed();

		$this->assertSame(['_posts/2022-03-29.rekindling-the-flame.md', '_posts/hello/index.md', 'old.md'], array_map(static fn (TrashedEntry $entry): string => $entry->entry, $trashed));
		$this->assertSame('Rekindling the Flame', $trashed[0]->title());
		$this->assertTrue($trashed[1]->bundle);
		$this->assertSame('20250101-090000/old.md', $trashed[2]->id);
		$this->assertSame('2025-01-01 09:00:00', $trashed[2]->trashed->format('Y-m-d H:i:s'));
	}

	public function testReadsAnEntryInTheTrash(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');

		$id     = $this->writer()->trashed()[0]->id ?? '';
		$loaded = $this->writer()->loadTrashed($id);

		$this->assertSame('_posts/2022-03-29.rekindling-the-flame.md', $loaded->id);
		$this->assertSame('Rekindling the Flame', $loaded->frontMatter['title'] ?? null);
		$this->assertSame("The body.\n", ltrim($loaded->body));

		$this->expectException(WriteException::class);

		$this->writer()->loadTrashed('20250101-090000/nope.md');
	}

	public function testRestoresWithChangesAndNeverLive(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';
		$this->writer()->delete($id);
		$trashed = $this->writer()->trashed()[0];

		$restored = $this->writer()->restore($trashed->id, new EntryChanges(set: ['status' => 'draft']));

		$this->assertSame($id, $restored->id);
		$this->assertStringContainsString("category  : [life]\nstatus: draft\n", $this->file($id), 'The rest of the file is as it was.');
		$this->assertSame(Status::Draft, $this->content()->find($id)?->status);
		$this->assertSame([], $this->writer()->trashed());
		$this->assertSame([], glob($this->temporaryDirectory() . '/storage/trash/*') ?: [], 'Nothing is left behind.');
	}

	public function testRestoresABundleWithItsMedia(): void
	{
		$this->writer()->delete('_posts/hello/index.md');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg');

		$this->writer()->restore($this->writer()->trashed()[0]->id);

		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg');
		$this->assertNotNull($this->content()->find('_posts/hello/index.md'));
	}

	public function testWontRestoreOverSomethingNew(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';
		$this->writer()->delete($id);
		$this->writeTemporaryFile("user/content/{$id}", "---\ntitle: A new one\n---\n");

		try {
			$this->writer()->restore($this->writer()->trashed()[0]->id);
			$this->fail('Restored over a new file.');
		} catch (WriteException $e) {
			$this->assertStringContainsString('something else is there now', $e->getMessage());
		}

		$this->assertCount(1, $this->writer()->trashed(), 'It stays in the trash.');
		$this->assertStringContainsString('A new one', $this->file($id));
	}

	public function testPurgesForGood(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');
		$this->writer()->delete('_posts/hello/index.md');

		foreach ($this->writer()->trashed() as $trashed) {
			$this->writer()->purge($trashed->id);
		}

		$this->assertSame([], glob($this->temporaryDirectory() . '/storage/trash/*') ?: []);

		foreach (['missing', '../../../config/app.php', '20260601-120000-abcdef/../../x.md'] as $id) {
			try {
				$this->writer()->purge($id);
				$this->fail("Purged {$id}.");
			} catch (WriteException) {
				$this->addToAssertionCount(1);
			}
		}
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
