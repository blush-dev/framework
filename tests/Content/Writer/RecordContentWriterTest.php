<?php

/**
 * Record content writer test.
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
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Record\EntryTable;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\RecordContentWriter;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\Application;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Tests\Content\BuildsContentSite;

/**
 * Content written as records, on a site that keeps its content in
 * SQLite (D-662): what a database keeps that files don't need to.
 */
#[CoversClass(RecordContentWriter::class)]
final class RecordContentWriterTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private Entries $content;

	protected function setUp(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'sqlite']);\n");
		$this->contentConfig([
			'types'     => [
				'post'     => ['urls' => ['prefix' => 'posts'], 'collection' => ['order' => 'desc']],
				'category' => ['urls' => ['prefix' => 'topics']],
				'chapter'  => ['urls' => ['prefix' => 'chapters'], 'hierarchical' => true]
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category']]
			]
		]);

		$this->app     = $this->site();
		$this->content = $this->app->container()->make(Entries::class);
	}

	/**
	 * Returns a type.
	 */
	private function type(string $name): ContentType
	{
		return $this->app->container()->make(ContentTypes::class)->get($name);
	}

	/**
	 * Creates a category.
	 */
	private function category(string $slug): Entry
	{
		return $this->content->create($this->type('category'), $slug, new EntryChanges(set: ['title' => ucfirst($slug)]));
	}

	public function testTheDriverWritesRecords(): void
	{
		$this->assertInstanceOf(RecordContentWriter::class, $this->app->container()->make(ContentWriter::class));
		$this->assertSame('Art', $this->category('art')->title);
		$this->assertFileExists($this->temporaryDirectory() . '/storage/database/site.sqlite');
	}

	public function testKeepsFrontMatterAsWrittenBesideItsValues(): void
	{
		$post = $this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello', 'date' => '2020-01-02 03:04:05', 'excerpt' => 'Hi.', 'mood' => 'sunny']));
		$file = $this->content->editable($post);

		$this->assertSame('2020-01-02 03:04:05', $file->frontMatter['date'] ?? null, 'As written, and no date of now.');
		$this->assertSame('Hi.', $file->frontMatter['excerpt'] ?? null, 'As written, alias and all.');
		$this->assertSame('sunny', $file->frontMatter['mood'] ?? null);
		$this->assertSame($post->id, $file->frontMatter['id'] ?? null);
		$this->assertSame(2020, (int) $post->date?->format('Y'), 'Read through its type.');
		$this->assertNull($post->field('mood'), 'Nothing declares it, so it isn\'t read (D-786).');

		$post = $this->content->change($post, new EntryChanges(set: ['title' => 'Hello Again']), $post->version);

		$this->assertSame('sunny', $this->content->editable($post)->frontMatter['mood'] ?? null, 'A change keeps it in the file.');

		$changed = $this->content->change($post, new EntryChanges(set: ['date' => '2021-05-06 07:08:09', 'summary' => 'Hello.'], remove: ['mood']), $post->version);
		$file    = $this->content->editable($changed);

		$this->assertSame('2021-05-06 07:08:09', $file->frontMatter['date'] ?? null);
		$this->assertSame('Hello.', $file->frontMatter['excerpt'] ?? null, 'Set where its alias is.');
		$this->assertArrayNotHasKey('summary', $file->frontMatter);
		$this->assertArrayNotHasKey('mood', $file->frontMatter);
		$this->assertSame(2021, (int) $changed->date?->format('Y'));
	}

	public function testRelationsAreRefsLoadedByTheirSlugsNow(): void
	{
		$art  = $this->category('art');
		$post = $this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello', 'category' => ['art']]));
		$art  = $this->content->rename($art, 'arts', $art->version);
		$file = $this->content->editable((string) $post->id);

		$this->assertSame(['arts'], $file->frontMatter['category'] ?? null, 'Never stale.');
		$this->assertSame(['arts'], $this->content->find((string) $post->id)?->slugs('category'));

		$stored = $this->app->container()->make(RecordStores::class)->store(EntryTable::table())->find(EntryTable::table(), (string) $post->id);

		$this->assertArrayNotHasKey('category', (array) ($stored->fields[RecordContentWriter::WRITTEN] ?? []), 'Kept as refs, not as written.');
	}

	public function testRefusesAValueThatNamesNoEntry(): void
	{
		$this->expectException(WriteException::class);
		$this->expectExceptionMessage('"nowhere" names no category.');

		$this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello', 'category' => ['nowhere']]));
	}

	public function testAHierarchicalCollectionsParentIsAColumn(): void
	{
		$one = $this->content->create($this->type('chapter'), 'one', new EntryChanges(set: ['title' => 'One']));
		$two = $this->content->create($this->type('chapter'), 'two', new EntryChanges(set: ['title' => 'Two', 'parent' => 'one']));

		$this->assertSame($one->id, $two->parentId);
		$this->assertSame('one', $this->content->editable($two)->frontMatter['parent'] ?? null);
	}

	public function testOnlyCoresOwnPlacesAreHiddenByTheirKeys(): void
	{
		$type   = $this->type('page');
		$home   = $this->content->createAt($type, 'index', new EntryChanges(set: ['title' => 'Home']));
		$cooks  = $this->content->createAt($type, '_cooks', new EntryChanges(set: ['title' => 'Cooks']));
		$errors = $this->content->createAt($type, '_errors', new EntryChanges(set: ['title' => 'Errors']));
		$gone   = $this->content->createAt($type, '_errors/404', new EntryChanges(set: ['title' => 'Not Found']));
		$quiet  = $this->content->createAt($type, 'quiet', new EntryChanges(set: ['title' => 'Quiet', 'visibility' => 'unlisted']));

		$this->assertTrue($home->landing);
		$this->assertSame('Home', $this->content->editableAt($type, 'index')?->frontMatter['title'] ?? null);
		$this->assertSame(Visibility::Public, $cooks->visibility, 'A `_` key means nothing of itself (D-855).');
		$this->assertSame(Visibility::Hidden, $errors->visibility, 'The error pages\' folder is core\'s.');
		$this->assertSame(Visibility::Hidden, $gone->visibility);
		$this->assertSame('_errors/404', $gone->key);
		$this->assertSame(Visibility::Unlisted, $quiet->visibility);
	}

	public function testWritesMoveTheContentVersionOnAndAreCheckedByVersion(): void
	{
		$versions = $this->app->container()->make(ContentVersion::class);
		$before   = $versions->current();
		$post     = $this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello']));

		$this->assertNotSame($before, $versions->current());

		$this->content->change($post, new EntryChanges(set: ['title' => 'Changed']), $post->version);

		$this->expectException(WriteConflict::class);

		$this->content->delete($post, $post->version);
	}
}
