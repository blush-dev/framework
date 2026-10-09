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

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Cache\ContentVersion;
use Blush\Content\ContentRepository;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Content\Writer\WriteResult;
use Blush\Core\Application;
use Blush\Support\Uuid;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(FilesystemWriter::class)]
#[CoversClass(DocumentEditor::class)]
#[CoversClass(EntryChanges::class)]
#[CoversClass(EditableEntry::class)]
#[CoversClass(WriteResult::class)]
#[CoversClass(AssignedIds::class)]
final class FilesystemWriterTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A jtcom post: aligned keys, the 1.x `date`, empty values, a blank
	 * line after the front matter.
	 */
	private const string POST = "---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\nformat    :\ncategory  : [life]\nid        : " . self::ID . "\n---\n\nThe body.\n";

	/**
	 * The post's id.
	 */
	private const string ID = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74';

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

	/**
	 * Returns the `refs` Blush files for the post's author (D-596).
	 */
	private function authorRefs(): string
	{
		return "refs:\n  authors:\n    justintadlock: " . $this->content()->findPath('profiles/j/justintadlock.md')?->id . "\n";
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
		$this->assertSame(RecordBuilder::hash(self::POST), $entry->revision);
		$this->assertSame(filemtime($this->temporaryDirectory() . '/user/content/_posts/2022-03-29.rekindling-the-flame.md'), $entry->modified);
	}

	public function testUpdatesOnlyWhatChanged(): void
	{
		$id      = '_posts/2022-03-29.rekindling-the-flame.md';
		$version = $this->app->container()->make(ContentVersion::class)->current();

		$result = $this->writer()->update($id, new EntryChanges(set: ['status' => 'draft', 'published' => '2022-04-01 09:00:00 -05:00']), $this->writer()->load($id)->revision);

		$this->assertSame(
			"---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-04-01 09:00:00 -05:00\nformat    :\ncategory  : [life]\nstatus: draft\n" . $this->authorRefs() . "id        : " . self::ID . "\n---\n\nThe body.\n",
			$this->file($id),
			'The author\'s id is filed under refs (D-596).'
		);
		$this->assertSame(RecordBuilder::hash($this->file($id)), $result->revision);
		$this->assertContains($id, $result->index->changed);
		$this->assertNotSame($version, $this->app->container()->make(ContentVersion::class)->current());
		$this->assertSame(Status::Draft, $this->content()->findPath($id)?->status);
	}

	public function testLeavesTheIdToTheWriter(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->update($id, new EntryChanges(set: ['id' => '9f8b1c2e-4d5a-4b6c-8d7e-0f1a2b3c4d5e', 'mood' => 'hopeful']));
		$this->writer()->update($id, new EntryChanges(remove: ['id']));

		$this->assertStringContainsString("mood: hopeful\n" . $this->authorRefs() . "id        : " . self::ID . "\n---", $this->file($id), 'An edit neither changes nor removes the id (D-477).');
		$this->assertSame($id, $this->content()->find(self::ID)?->path);
	}

	public function testAssignsIds(): void
	{
		$this->writeTemporaryFile('user/content/topics/age.md', "---\ntitle: Age\n---\n\nAbout ages.\n");
		$this->writeTemporaryFile('user/content/topics/odd.md', "---\nid: 42\ntitle: Odd\n---\n");
		$this->app = $this->site('development');

		$assigned = $this->writer()->assignIds(['topics/age.md', 'topics/odd.md', 'topics/gone.md', 'topics/age.md']);

		$this->assertSame(['topics/age.md', 'topics/odd.md'], array_keys($assigned->ids), 'Each file once.');
		$this->assertSame(['topics/gone.md'], array_keys($assigned->failed), 'A file that isn\'t there is left out.');
		$this->assertTrue(array_all($assigned->ids, static fn (string $id): bool => Uuid::isValid($id)));
		$this->assertSame("---\ntitle: Age\nid: {$assigned->ids['topics/age.md']}\n---\n\nAbout ages.\n", $this->file('topics/age.md'), 'Added last.');
		$this->assertSame("---\nid: {$assigned->ids['topics/odd.md']}\ntitle: Odd\n---\n", $this->file('topics/odd.md'), 'A bad one is replaced in its place.');
		$this->assertSame('topics/odd.md', $this->content()->find($assigned->ids['topics/odd.md'])?->path, 'The index has them.');
		$this->assertSame([], $this->writer()->assignIds([])->ids);
	}

	public function testReplacesTheBodyAndRemovesKeys(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->update($id, new EntryChanges(remove: ['format'], body: "\nNew words.\n"));

		$this->assertSame("---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\ncategory  : [life]\n" . $this->authorRefs() . "id        : " . self::ID . "\n---\n\nNew words.\n", $this->file($id));
	}

	public function testKeepsAFileThatEndsAtItsFrontMatter(): void
	{
		$this->writeTemporaryFile('user/content/topics/age.md', "---\ntitle : Age\nid    : " . self::ID . "\n---");
		$this->app = $this->site('development');

		$this->writer()->update('topics/age.md', new EntryChanges(set: ['title' => 'Ages']));
		$this->assertSame("---\ntitle : Ages\nid    : " . self::ID . "\n---", $this->file('topics/age.md'));

		$this->writer()->update('topics/age.md', new EntryChanges(body: "About ages.\n"));
		$this->assertSame("---\ntitle : Ages\nid    : " . self::ID . "\n---\nAbout ages.\n", $this->file('topics/age.md'));
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
		$this->assertMatchesRegularExpression('/\A---\ntitle: Age\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\nAbout ages.\n\z/', $this->file('topics/age.md'), 'A file without an id is given one, last (D-477).');
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

		$this->assertSame('_posts/fresh-start.md', $post->path, 'The slug alone by default, date archives or not (D-515).');
		$id = (string) $this->content()->findPath($post->path)?->id;

		$this->assertTrue(Uuid::isValid($id), 'A new entry has an id (D-477).');
		$this->assertSame("---\ntitle: \"A Fresh Start\"\nstatus: draft\npublished: 2026-06-01 12:00:00 -05:00\nid: {$id}\n---\n\nHello.\n", $this->file($post->path), 'Its id is last, after a publish date (D-514).');
		$this->assertSame('A Fresh Start', $this->content()->find($id)?->title);

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('already exists');

		$this->writer()->create($this->app->container()->make(ContentTypes::class)->get('post'), 'fresh-start', new EntryChanges(set: ['title' => 'Again']));
	}

	public function testNamesNewFilesByTheTypesPattern(): void
	{
		$this->contentConfig(['types' => ['note' => ['path' => '_notes', 'filename' => '{date}-{time}.{slug}']]]);
		$this->app = $this->site('development');

		$type = $this->app->container()->make(ContentTypes::class)->get('note');
		$note = $this->writer()->create($type, 'jotted', new EntryChanges(set: ['title' => 'Jotted']), new DateTimeImmutable('2026-10-05 09:30:15'));

		$this->assertSame('_notes/2026-10-05-093015.jotted.md', $note->path, 'D-511');
		$this->assertSame('jotted', $this->content()->findPath($note->path)?->slug);

		$renamed = $this->writer()->rename($note->path, 'scribbled');

		$this->assertSame('_notes/2026-10-05-093015.scribbled.md', $renamed->path, 'A rename keeps the prefix, whatever pattern named the file.');
	}

	public function testNamesATreesPagesByItsPatternButNotItsFolders(): void
	{
		$this->contentConfig(['types' => ['doc' => ['kind' => 'tree', 'folder' => '_docs', 'filename' => 'doc.{slug}']]]);
		$this->entry('_docs/install.md', 'title: Install');
		$this->app = $this->site('development');

		$type  = $this->app->container()->make(ContentTypes::class)->get('doc');
		$intro = $this->writer()->create($type, 'intro', new EntryChanges(set: ['title' => 'Intro']));
		$child = $this->writer()->createUnder('_docs/install.md', 'requirements', new EntryChanges(set: ['title' => 'Requirements']));

		$this->assertSame('_docs/doc.intro.md', $intro->path, 'Any kind of type (D-514).');
		$this->assertSame('_docs/install/doc.requirements.md', $child->path, 'Its folder is the parent\'s key (D-513).');
		$this->assertSame('install/requirements', $this->content()->findPath($child->path)?->key);
	}

	public function testCreatesPagesAtTheirKeys(): void
	{
		$post = $this->app->container()->make(ContentTypes::class)->get('post');
		$page = $this->writer()->createAt($post, '_authors/jane', new EntryChanges(set: ['title' => 'Jane, Blogger']));

		$this->assertSame('_posts/_authors/jane.md', $page->path, 'At its key, with no pattern (D-353).');
		$this->assertSame("---\ntitle: \"Jane, Blogger\"\npublished: 2026-06-01 12:00:00 -05:00\nid: {$this->content()->findPath($page->path)?->id}\n---\n", $this->file($page->path));

		foreach (['__authors/jane', '_authors/Jane Doe', '../escape', '_authors//jane'] as $key) {
			try {
				$this->writer()->createAt($post, $key, new EntryChanges());
				$this->fail($key);
			} catch (WriteException $e) {
				$this->assertStringContainsString('isn\'t a page key', $e->getMessage(), $key);
			}
		}

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('already exists');

		$this->writer()->createAt($post, '_authors/jane', new EntryChanges());
	}

	public function testCreatesPagesUnderAPageInAFolder(): void
	{
		$page = $this->writer()->createUnder('about/index.md', 'team', new EntryChanges(set: ['title' => 'The Team']));

		$this->assertSame('about/team.md', $page->path);
		$this->assertSame([], $page->moved, 'A folder\'s page stays where it is (D-408).');
		$this->assertSame('about/team', $this->content()->findPath($page->path)?->key);
		$this->assertSame('about', $this->content()->parentKey('page', 'about/team'));

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('already a page at about/biography');

		$this->writer()->createUnder('about/index.md', 'biography', new EntryChanges(set: ['title' => 'Again']));
	}

	public function testMakesAParentKeptAsAFileItsFoldersPage(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n\nWhat I do.\n");
		$this->app = $this->site('development');

		$page = $this->writer()->createUnder('services.md', 'writing', new EntryChanges(set: ['title' => 'Writing']));

		$this->assertSame('services/writing.md', $page->path);
		$this->assertSame(['services.md' => 'services/index.md'], $page->moved);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/services.md');
		$this->assertSame("---\ntitle: Services\n---\n\nWhat I do.\n", $this->file('services/index.md'), 'Moved as it was.');
		$this->assertSame('services', $this->content()->findPath('services/index.md')?->key, 'Its key, and so its address, stay the same.');
		$this->assertSame('services', $this->content()->parentKey('page', 'services/writing'));

		$second = $this->writer()->createUnder('services/index.md', 'design', new EntryChanges(set: ['title' => 'Design']));
		$this->assertSame([], $second->moved);
	}

	public function testLeavesAParentWhoseFileNameSaysMore(): void
	{
		$this->writeTemporaryFile('user/content/01.services.md', "---\ntitle: Services\n---\n");
		$this->app = $this->site('development');

		$page = $this->writer()->createUnder('01.services.md', 'writing', new EntryChanges(set: ['title' => 'Writing']));

		$this->assertSame('services/writing.md', $page->path, 'In the folder its key names, beside the parent.');
		$this->assertSame([], $page->moved, 'An order prefix would be lost in the move.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/01.services.md');
		$this->assertSame('services', $this->content()->parentKey('page', 'services/writing'));
	}

	public function testRefusesParentsThatCantHavePages(): void
	{
		foreach (['_posts/2022-03-29.rekindling-the-flame.md' => 'isn\'t a page other pages', 'index.md' => 'index page', 'missing.md' => 'isn\'t a page other pages'] as $id => $message) {
			try {
				$this->writer()->createUnder($id, 'child', new EntryChanges(set: ['title' => 'Child']));
				$this->fail($id);
			} catch (WriteException $e) {
				$this->assertStringContainsString($message, $e->getMessage(), $id);
			}
		}

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('isn\'t a slug');

		$this->writer()->createUnder('about/index.md', 'Not A Slug', new EntryChanges());
	}

	public function testMovesNothingWhenTheParentsFolderHasAPage(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n");
		$this->writeTemporaryFile('user/content/services/index.md', "---\ntitle: Other Services\n---\n");
		$this->app = $this->site('development');

		try {
			$this->writer()->createUnder('services.md', 'writing', new EntryChanges(set: ['title' => 'Writing']));
			$this->fail('Moved a page onto another.');
		} catch (WriteException $e) {
			$this->assertStringContainsString('that folder already has a page', $e->getMessage());
		}

		$this->assertFileExists($this->temporaryDirectory() . '/user/content/services.md');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/services/writing.md');
	}

	public function testMovesAPageUnderAnother(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n");
		$this->app = $this->site('development');

		$result = $this->writer()->move('services.md', 'about/index.md', $this->writer()->load('services.md')->revision);

		$this->assertSame('about/services.md', $result->path);
		$this->assertSame(['services.md' => 'about/services.md'], $result->moved);
		$this->assertSame('about', $this->content()->parentKey('page', 'about/services'), 'Under its new parent (D-410).');

		$top = $this->writer()->move('about/services.md', null);

		$this->assertSame('services.md', $top->path, 'And back to the top.');
		$this->assertSame([], $this->writer()->move('services.md', null)->moved, 'Where it is already, nothing moves.');
	}

	public function testMovesAPagesFolderWithThePagesUnderIt(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n");
		$this->app = $this->site('development');

		$result = $this->writer()->move('about/index.md', 'services.md');

		$this->assertSame('services/about/index.md', $result->path);
		$this->assertSame([
			'services.md'        => 'services/index.md',
			'about/biography.md' => 'services/about/biography.md',
			'about/index.md'     => 'services/about/index.md'
		], $result->moved, 'The new parent became its folder\'s page, and the page under it came along.');
		$this->assertSame('services/about', $this->content()->findPath('services/about/biography.md')?->type->parentKey('services/about/biography', []));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/content/about');
	}

	public function testMovesAPageKeptAsAFileWithTheFolderBesideIt(): void
	{
		$this->writeTemporaryFile('user/content/work.md', "---\ntitle: Work\n---\n");
		$this->writeTemporaryFile('user/content/work/design.md', "---\ntitle: Design\n---\n");
		$this->app = $this->site('development');

		$result = $this->writer()->move('work.md', 'about/index.md');

		$this->assertSame('about/work.md', $result->path);
		$this->assertSame(['work.md' => 'about/work.md', 'work/design.md' => 'about/work/design.md'], $result->moved);
		$this->assertSame('about/work', $this->content()->parentKey('page', 'about/work/design'));
	}

	public function testRefusesMovesItCantMake(): void
	{
		$this->writeTemporaryFile('user/content/biography.md', "---\ntitle: Another Biography\n---\n");
		$this->app = $this->site('development');

		$refused = [
			['about/index.md', 'about/biography.md', 'can\'t go under itself or a page under it'],
			['about/index.md', 'about/index.md', 'can\'t go under itself'],
			['index.md', 'about/index.md', 'index page'],
			['_posts/2022-03-29.rekindling-the-flame.md', 'about/index.md', 'isn\'t a page that can move'],
			['biography.md', 'about/index.md', 'already a page at about/biography'],
			['biography.md', '_posts/2022-03-29.rekindling-the-flame.md', 'isn\'t one of the pages']
		];

		foreach ($refused as [$id, $parent, $message]) {
			try {
				$this->writer()->move($id, $parent);
				$this->fail("{$id} under {$parent}");
			} catch (WriteException $e) {
				$this->assertStringContainsString($message, $e->getMessage(), "{$id} under {$parent}");
			}
		}

		$this->assertFileExists($this->temporaryDirectory() . '/user/content/biography.md');

		$stale = $this->writer()->load('biography.md')->revision;
		$this->writer()->update('biography.md', new EntryChanges(set: ['title' => 'Changed']));

		$this->expectException(WriteConflict::class);

		$this->writer()->move('biography.md', null, $stale);
	}

	public function testWritesOnlyMarkdownFiles(): void
	{
		$this->writeTemporaryFile('user/content/data.yaml', "title: Data\n");
		$this->app = $this->site('development');

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('"data.yaml" isn\'t a content file.');

		$this->writer()->update('data.yaml', new EntryChanges(set: ['title' => 'Better Data']));
	}

	public function testRenamesKeepingTheDateOrTheBundle(): void
	{
		$renamed = $this->writer()->rename('_posts/2022-03-29.rekindling-the-flame.md', 'the-flame');

		$this->assertSame('_posts/2022-03-29.the-flame.md', $renamed->path);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/2022-03-29.the-flame.md');
		$this->assertNull($this->content()->findPath('_posts/2022-03-29.rekindling-the-flame.md'));

		$bundle = $this->writer()->rename('_posts/hello/index.md', 'greetings');

		$this->assertSame('_posts/greetings/index.md', $bundle->path);
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

		$this->assertSame('_posts/the-copy.md', $first->path, 'Named by the slug alone (D-515).');
		$this->assertSame('_posts/the-copy-2.md', $second->path, 'A taken name gets a number.');
		$copy = (string) $this->content()->findPath($first->path)?->id;

		$this->assertTrue(Uuid::isValid($copy));
		$this->assertNotSame(self::ID, $copy, 'A copy has an id of its own (D-477).');
		$this->assertSame("---\ntitle     : \"The Copy\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\nformat    :\ncategory  : [life]\nstatus: draft\n" . $this->authorRefs() . "id        : {$copy}\n---\n\nThe body.\n", $this->file($first->path), 'In its place, before the new key.');
		$this->assertSame(self::POST, $this->file($id), 'The original is untouched.');
		$this->assertSame('The Copy', $this->content()->findPath($first->path)?->title);

		$bundle = $this->writer()->duplicate('_posts/hello/index.md', 'hello-copy', new EntryChanges(set: ['title' => 'Hello (Copy)']));

		$this->assertSame('_posts/hello-copy.md', $bundle->path, 'A collection\'s copy is a file (D-514).');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/hello-copy/photo.jpg');

		$page = $this->writer()->duplicate('about/index.md', 'about-copy', new EntryChanges(set: ['title' => 'About (Copy)']));

		$this->assertSame('about-copy/index.md', $page->path, 'A page\'s folder is copied, named by its slug alone (D-513).');

		$this->expectException(WriteException::class);

		$this->writer()->duplicate('_posts/index.md', 'blog', $changes);
	}

	public function testTrashingKeepsTheFileInPlace(): void
	{
		$path = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->trash($path);

		$file  = $this->file($path);
		$entry = $this->content()->find(self::ID);

		$this->assertStringContainsString("status: trash\n", $file, 'It stays where it is, marked (D-484).');
		$this->assertStringContainsString("trashed: 2026-06-01 12:00:00 -05:00\n", $file, 'With when, in the site\'s timezone.');
		$this->assertSame($path, $entry?->path, 'It keeps its id.');
		$this->assertSame(Status::Trash, $entry->status);
		$this->assertSame(1, $this->content()->query()->any()->status(Status::Trash)->count(), 'Asked for by name, the trash is found.');
		$this->assertSame(0, $this->content()->query()->any()->names('rekindling-the-flame')->count(), 'Any status means every one but the trash.');
	}

	public function testRestoresAsADraft(): void
	{
		$path = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->trash($path);
		$this->writer()->restore($path);

		$file = $this->file($path);

		$this->assertStringContainsString("category  : [life]\nstatus: draft\n", $file, 'The rest of the file is as it was.');
		$this->assertStringNotContainsString('trashed', $file);
		$this->assertSame(Status::Draft, $this->content()->findPath($path)?->status);
	}

	public function testDeletesForGood(): void
	{
		$path = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->delete($path);

		$this->assertFileDoesNotExist($this->temporaryDirectory() . "/user/content/{$path}");
		$this->assertNull($this->content()->find(self::ID));

		$this->writer()->delete('_posts/hello/index.md');

		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/hello/index.md');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg', 'A bundle\'s folder stays while something else is in it.');

		$this->writeTemporaryFile('user/content/_posts/alone/index.md', "---\ntitle: Alone\n---\n");
		$this->writer()->delete('_posts/alone/index.md');

		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/alone', 'An empty one goes with it.');

		$this->expectException(WriteException::class);

		$this->writer()->delete('_posts/missing.md');
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
