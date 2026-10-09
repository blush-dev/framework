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
		$this->assertFileExists($this->temporaryDirectory() . '/user/site.sqlite');
	}

	public function testKeepsFrontMatterAsWrittenBesideItsValues(): void
	{
		$post = $this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello', 'date' => '2020-01-02 03:04:05', 'mood' => 'sunny']));
		$file = $this->content->editable($post);

		$this->assertSame('2020-01-02 03:04:05', $file->frontMatter['date'] ?? null, 'As written, alias and all.');
		$this->assertArrayNotHasKey('published', $file->frontMatter, 'No date of now, since `date` is one.');
		$this->assertSame('sunny', $file->frontMatter['mood'] ?? null);
		$this->assertSame($post->id, $file->frontMatter['id'] ?? null);
		$this->assertSame(2020, (int) $post->published?->format('Y'), 'Read through its type.');
		$this->assertSame('sunny', $post->extra['mood'] ?? null);

		$changed = $this->content->change($post, new EntryChanges(set: ['published' => '2021-05-06 07:08:09'], remove: ['mood']), $post->version);
		$file    = $this->content->editable($changed);

		$this->assertSame('2021-05-06 07:08:09', $file->frontMatter['date'] ?? null, 'Set where its alias is.');
		$this->assertArrayNotHasKey('published', $file->frontMatter);
		$this->assertArrayNotHasKey('mood', $file->frontMatter);
		$this->assertSame(2021, (int) $changed->published?->format('Y'));
	}

	public function testRelationsAreRefsLoadedByTheirSlugsNow(): void
	{
		$art  = $this->category('art');
		$post = $this->content->create($this->type('post'), 'hello', new EntryChanges(set: ['title' => 'Hello', 'category' => ['art']]));
		$art  = $this->content->rename($art, 'arts', $art->version);
		$file = $this->content->editable((string) $post->id);

		$this->assertSame(['arts'], $file->frontMatter['category'] ?? null, 'Never stale.');
		$this->assertSame(['arts'], $this->content->find((string) $post->id)?->terms('category'));

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

	public function testPagesAtKeysWithUnderscoresAreHidden(): void
	{
		$type  = $this->type('page');
		$home  = $this->content->createAt($type, 'index', new EntryChanges(set: ['title' => 'Home']));
		$cooks = $this->content->createAt($type, '_cooks', new EntryChanges(set: ['title' => 'Cooks']));
		$jane  = $this->content->createAt($type, '_cooks/jane', new EntryChanges(set: ['title' => 'Jane']));

		$this->assertTrue($home->landing);
		$this->assertSame('Home', $this->content->editableAt($type, 'index')?->frontMatter['title'] ?? null);
		$this->assertSame(Visibility::Hidden, $cooks->visibility);
		$this->assertSame(Visibility::Hidden, $jane->visibility, 'Under a hidden page.');
		$this->assertSame('_cooks/jane', $jane->key);
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
