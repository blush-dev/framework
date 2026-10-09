<?php

/**
 * Index store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Index\IndexFreshness;
use Blush\Content\Index\IndexStore;
use Blush\Content\Record\EntryTable;
use Blush\Core\Application;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\StorageArea;

#[CoversClass(IndexStore::class)]
#[CoversClass(IndexFreshness::class)]
#[CoversClass(FileRecordStore::class)]
final class IndexStoreTest extends TestCase
{
	use BuildsContentSite;

	private const string NEW = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eff';

	private Application $app;

	private RecordStore $store;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->app   = $this->site();
		$this->store = $this->app->container()->make(RecordStores::class)->store(EntryTable::table());
	}

	/**
	 * An entry's record, by type and slug.
	 */
	private function stored(string $type, string $slug): Record
	{
		$found = $this->store->select(EntryTable::table(), new RecordQuery()->where('type', '=', $type)->where('slug', '=', $slug))->first();

		$this->assertNotNull($found, "No {$type} \"{$slug}\".");

		return $found;
	}

	public function testTheContentAreaKeepsEntriesInTheIndex(): void
	{
		$this->assertInstanceOf(FileRecordStore::class, $this->store, 'The filesystem driver\'s record store…');

		$spring = $this->stored('post', 'spring');

		$this->assertSame("Spring is here.", trim((string) $spring->content), '…hands entries to the index store, a record found carrying its Markdown.');
		$this->assertNotNull($spring->version);
		$this->assertNull($this->store->select(EntryTable::table(), new RecordQuery()->where('slug', '=', 'spring')->withoutContent())->first()?->content, 'Or not, when the query leaves it out.');
		$this->assertSame($spring->id, $this->store->find(EntryTable::table(), $spring->id)?->id);
	}

	public function testChangesBecomeFrontMatterEditsAndMoves(): void
	{
		$spring = $this->stored('post', 'spring');
		$fields = is_array($spring->fields['fields'] ?? null) ? $spring->fields['fields'] : [];
		$saved  = $this->store->save(
			EntryTable::table(),
			$spring->withFields([...$spring->fields, 'title' => 'Spring Again', 'slug' => 'spring-again', 'fields' => [...$fields, 'tag' => 'blossoms']])->withContent("Spring is back.\n"),
			$spring->version
		);

		$this->assertSame('Spring Again', $saved->fields['title']);
		$this->assertSame('spring-again', $saved->fields['slug']);
		$this->assertNotSame($spring->version, $saved->version, 'A new version.');
		$this->assertSame($spring->id, $saved->id, 'The same entry.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_post/2008-04-05.spring-again.md', 'Renamed as files are, the date kept.');
		$this->assertStringContainsString("tag: blossoms", (string) file_get_contents($this->temporaryDirectory() . '/user/content/_post/2008-04-05.spring-again.md'));
		$this->assertSame("Spring is back.", trim((string) $saved->content));

		$this->expectException(RecordConflict::class);

		$this->store->save(EntryTable::table(), $spring->withFields([...$spring->fields, 'title' => 'Stale']), $spring->version);
	}

	public function testTrashRestoreAndDelete(): void
	{
		$rainy   = $this->stored('post', 'rainy');
		$trashed = $this->store->save(EntryTable::table(), $rainy->with('status', 'trash'), $rainy->version);

		$this->assertSame('trash', $trashed->fields['status']);
		$this->assertArrayHasKey('trashed', is_array($trashed->fields['fields'] ?? null) ? $trashed->fields['fields'] : [], 'Trashed as the writer trashes.');

		$restored = $this->store->save(EntryTable::table(), $trashed->with('status', 'draft'), $trashed->version);

		$this->assertSame('draft', $restored->fields['status']);

		$this->store->delete(EntryTable::table(), $restored->id, $restored->version);

		$this->assertNull($this->store->find(EntryTable::table(), $restored->id));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_post/2008-04-20.rainy.md');
	}

	public function testANewEntryKeepsItsId(): void
	{
		$record = new Record(self::NEW, [
			'type'      => 'post',
			'slug'      => 'summer',
			'title'     => 'Summer',
			'status'    => 'draft',
			'published' => '2026-07-01T14:00:00Z',
			'fields'    => ['tag' => 'sun']
		], "Hot.\n");

		$saved = $this->store->save(EntryTable::table(), $record);

		$this->assertSame(self::NEW, $saved->id);
		$this->assertSame('summer', $saved->fields['slug']);
		$this->assertSame('draft', $saved->fields['status']);
		$this->assertSame('2026-07-01T14:00:00Z', $saved->fields['published']);

		$files = glob($this->temporaryDirectory() . '/user/content/_post/*summer.md') ?: [];

		$this->assertCount(1, $files, 'Where its type keeps new entries.');
		$this->assertStringContainsString('id: ' . self::NEW, (string) file_get_contents($files[0]));
	}

	public function testATreePageMovesToANewParent(): void
	{
		$biography = $this->stored('page', 'biography');
		$notes     = $this->stored('page', 'notes');
		$moved     = $this->store->save(EntryTable::table(), $biography->with('parent_id', $notes->id), $biography->version);

		$this->assertSame($notes->id, $moved->fields['parent_id']);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/notes/biography.md');
	}

	public function testWhatFilesCantDoIsRefused(): void
	{
		$spring = $this->stored('post', 'spring');
		$cases  = [
			'a new type'             => fn () => $this->store->save(EntryTable::table(), $spring->with('type', 'category')),
			'a post\'s parent by id' => fn () => $this->store->save(EntryTable::table(), $spring->with('parent_id', $this->stored('post', 'rainy')->id)),
			'writing refs'           => fn () => $this->store->save(Ref::table(StorageArea::Content), new Ref($spring->id, 'category', $spring->id)->record())
		];

		foreach ($cases as $name => $case) {
			try {
				$case();
				$this->fail("Allowed {$name}.");
			} catch (InvalidRecord) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testRefsAreReadFromTheIndex(): void
	{
		$spring = $this->stored('post', 'spring');
		$refs   = $this->app->container()->make(RecordStores::class)->query(Ref::table(StorageArea::Content))->where('source_id', '=', $spring->id)->where('relation', '=', 'category')->orderBy('position')->get();

		$this->assertCount(2, $refs, 'Its two categories.');
	}
}
