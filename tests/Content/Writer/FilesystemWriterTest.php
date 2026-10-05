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
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\IdTaken;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\TrashedEntry;
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
#[CoversClass(TrashedEntry::class)]
#[CoversClass(AssignedIds::class)]
#[CoversClass(IdTaken::class)]
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
			"---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-04-01 09:00:00 -05:00\nformat    :\ncategory  : [life]\nstatus: draft\nid        : " . self::ID . "\n---\n\nThe body.\n",
			$this->file($id)
		);
		$this->assertSame(hash('sha256', $this->file($id)), $result->revision);
		$this->assertContains($id, $result->index->changed);
		$this->assertNotSame($version, $this->app->container()->make(ContentVersion::class)->current());
		$this->assertSame(Status::Draft, $this->content()->findPath($id)?->status);
	}

	public function testLeavesTheIdToTheWriter(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';

		$this->writer()->update($id, new EntryChanges(set: ['id' => '9f8b1c2e-4d5a-4b6c-8d7e-0f1a2b3c4d5e', 'mood' => 'hopeful']));
		$this->writer()->update($id, new EntryChanges(remove: ['id']));

		$this->assertStringContainsString("mood: hopeful\nid        : " . self::ID . "\n---", $this->file($id), 'An edit neither changes nor removes the id (D-477).');
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

		$this->assertSame("---\ntitle     : \"Rekindling the Flame\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\ncategory  : [life]\nid        : " . self::ID . "\n---\n\nNew words.\n", $this->file($id));
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

		$this->assertSame('_posts/2026-06-01.fresh-start.md', $post->path);
		$id = (string) $this->content()->findPath($post->path)?->id;

		$this->assertTrue(Uuid::isValid($id), 'A new entry has an id (D-477).');
		$this->assertSame("---\ntitle: \"A Fresh Start\"\nstatus: draft\nid: {$id}\n---\n\nHello.\n", $this->file($post->path), 'Its id is last.');
		$this->assertSame('A Fresh Start', $this->content()->find($id)?->title);

		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('already exists');

		$this->writer()->create($this->app->container()->make(ContentTypes::class)->get('post'), 'fresh-start', new EntryChanges(set: ['title' => 'Again']));
	}

	public function testCreatesPagesAtTheirKeys(): void
	{
		$post = $this->app->container()->make(ContentTypes::class)->get('post');
		$page = $this->writer()->createAt($post, '_authors/jane', new EntryChanges(set: ['title' => 'Jane, Blogger']));

		$this->assertSame('_posts/_authors/jane.md', $page->path, 'Undated, at its key (D-353).');
		$this->assertSame("---\ntitle: \"Jane, Blogger\"\nid: {$this->content()->findPath($page->path)?->id}\n---\n", $this->file($page->path));

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

		$this->writer()->createAt($post, '_authors/jane', new EntryChanges(), 'json');
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
		$this->writeTemporaryFile('user/content/services/index.yaml', "title: Other Services\n");
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

	public function testEditsJsonAndYamlEntries(): void
	{
		$this->writeTemporaryFile('user/content/data.yaml', "# Kept.\ntitle: Data\nbody: Old\n");
		$this->app = $this->site('development');

		$this->writer()->update('notes.json', new EntryChanges(set: ['title' => 'Better Notes'], body: 'New *notes*.'));
		$this->writer()->update('data.yaml', new EntryChanges(set: ['status' => 'draft'], body: "Line one\nLine two"));

		$this->assertSame("{\n    \"title\": \"Better Notes\",\n    \"body\": \"New *notes*.\",\n    \"id\": \"{$this->content()->findPath('notes.json')?->id}\"\n}\n", $this->file('notes.json'));
		$this->assertSame("# Kept.\ntitle: Data\nbody: |-\n  Line one\n  Line two\nstatus: draft\nid: {$this->content()->findPath('data.yaml')?->id}\n", $this->file('data.yaml'));

		$this->writer()->update('data.yaml', new EntryChanges(set: ['summary' => 'Short.']));
		$this->writer()->update('notes.json', new EntryChanges(set: ['summary' => 'Short.']));

		$this->assertStringEndsWith("summary: Short.\nid: {$this->content()->findPath('data.yaml')?->id}\n", $this->file('data.yaml'), 'A new key goes before the id, which stays last.');
		$this->assertStringEndsWith("\"summary\": \"Short.\",\n    \"id\": \"{$this->content()->findPath('notes.json')?->id}\"\n}\n", $this->file('notes.json'));
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

		$this->assertSame('_posts/2026-06-01.the-copy.md', $first->path, 'A dated copy takes today\'s date.');
		$this->assertSame('_posts/2026-06-01.the-copy-2.md', $second->path, 'A taken name gets a number.');
		$copy = (string) $this->content()->findPath($first->path)?->id;

		$this->assertTrue(Uuid::isValid($copy));
		$this->assertNotSame(self::ID, $copy, 'A copy has an id of its own (D-477).');
		$this->assertSame("---\ntitle     : \"The Copy\"\nauthor    : justintadlock\ndate      : 2022-03-29 23:00:00 -6\nformat    :\ncategory  : [life]\nstatus: draft\nid        : {$copy}\n---\n\nThe body.\n", $this->file($first->path), 'In its place, before the new key.');
		$this->assertSame(self::POST, $this->file($id), 'The original is untouched.');
		$this->assertSame('The Copy', $this->content()->findPath($first->path)?->title);

		$bundle = $this->writer()->duplicate('_posts/hello/index.md', 'hello-copy', new EntryChanges(set: ['title' => 'Hello (Copy)']));

		$this->assertSame('_posts/hello-copy/index.md', $bundle->path);
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
		$this->assertSame(['entry' => $id, 'id' => self::ID, 'bundle' => false, 'trashed' => '2026-06-01T12:00:00-05:00'], json_decode((string) file_get_contents(($folders[0] ?? '') . '/trash.json'), true));
		$this->assertNull($this->content()->findPath($id));
	}

	public function testListsTheTrash(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');
		$this->writer()->delete('_posts/hello/index.md');

		// Trash from before manifests is listed file by file.
		$this->writeTemporaryFile('storage/trash/20250101-090000/user/content/old.md', "---\ntitle: Old\n---\n");

		$trashed = $this->writer()->trashed();

		$this->assertSame(['_posts/2022-03-29.rekindling-the-flame.md', '_posts/hello/index.md', 'old.md'], array_map(static fn (TrashedEntry $entry): string => $entry->path, $trashed));
		$this->assertSame('Rekindling the Flame', $trashed[0]->title());
		$this->assertTrue($trashed[1]->bundle);
		$this->assertSame('20250101-090000/old.md', $trashed[2]->name);
		$this->assertSame(self::ID, $trashed[0]->id, 'Its id, recorded when it was trashed (D-481).');
		$this->assertNull($trashed[2]->id, 'One from before ids has none.');
		$this->assertSame('2025-01-01 09:00:00', $trashed[2]->trashed->format('Y-m-d H:i:s'));
	}

	public function testReadsAnEntryInTheTrash(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');

		$id     = $this->writer()->trashed()[0]->name ?? '';
		$loaded = $this->writer()->loadTrashed($id);

		$this->assertSame('_posts/2022-03-29.rekindling-the-flame.md', $loaded->path);
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

		$restored = $this->writer()->restore($trashed->name, new EntryChanges(set: ['status' => 'draft']));

		$this->assertSame($id, $restored->path);
		$this->assertStringContainsString("category  : [life]\nstatus: draft\n", $this->file($id), 'The rest of the file is as it was.');
		$this->assertSame(Status::Draft, $this->content()->findPath($id)?->status);
		$this->assertSame([], $this->writer()->trashed());
		$this->assertSame([], glob($this->temporaryDirectory() . '/storage/trash/*') ?: [], 'Nothing is left behind.');
	}

	public function testRestoresABundleWithItsMedia(): void
	{
		$this->writer()->delete('_posts/hello/index.md');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg');

		$this->writer()->restore($this->writer()->trashed()[0]->name);

		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/hello/photo.jpg');
		$this->assertNotNull($this->content()->findPath('_posts/hello/index.md'));
	}

	public function testWontRestoreOverSomethingNew(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';
		$this->writer()->delete($id);
		$this->writeTemporaryFile("user/content/{$id}", "---\ntitle: A new one\n---\n");

		try {
			$this->writer()->restore($this->writer()->trashed()[0]->name);
			$this->fail('Restored over a new file.');
		} catch (WriteException $e) {
			$this->assertStringContainsString('something else is there now', $e->getMessage());
		}

		$this->assertCount(1, $this->writer()->trashed(), 'It stays in the trash.');
		$this->assertStringContainsString('A new one', $this->file($id));
	}

	public function testAsksBeforeRestoringAnIdAnotherEntryHas(): void
	{
		$id = '_posts/2022-03-29.rekindling-the-flame.md';
		$this->writeTemporaryFile('user/content/_posts/2022-04-01.copy.md', "---\ntitle: The Copy\nid: " . self::ID . "\n---\n");
		$this->app = $this->site('development');
		$this->writer()->delete($id);

		$this->assertSame('_posts/2022-04-01.copy.md', $this->content()->find(self::ID)?->path, 'The copy has the id now.');

		try {
			$this->writer()->restore($this->writer()->trashed()[0]->name);
			$this->fail('Restored with an id another entry has.');
		} catch (IdTaken $e) {
			$this->assertSame([self::ID, '_posts/2022-04-01.copy.md'], [$e->id, $e->holder]);
			$this->assertStringContainsString('“The Copy” (_posts/2022-04-01.copy.md) has its id now', $e->getMessage());
		}

		$this->assertCount(1, $this->writer()->trashed(), 'It stays in the trash until someone chooses (D-481).');

		$this->writer()->restore($this->writer()->trashed()[0]->name, newId: true);

		$restored = (string) $this->content()->findPath($id)?->id;

		$this->assertTrue(Uuid::isValid($restored));
		$this->assertNotSame(self::ID, $restored, 'With a new id, when asked.');
		$this->assertSame('_posts/2022-04-01.copy.md', $this->content()->find(self::ID)?->path, 'The copy keeps its id.');
	}

	public function testPurgesForGood(): void
	{
		$this->writer()->delete('_posts/2022-03-29.rekindling-the-flame.md');
		$this->writer()->delete('_posts/hello/index.md');

		foreach ($this->writer()->trashed() as $trashed) {
			$this->writer()->purge($trashed->name);
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
