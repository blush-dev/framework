<?php

/**
 * Record test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\Record\Condition;
use Blush\Storage\Record\ConditionGroup;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\InvalidRecordQuery;
use Blush\Storage\Record\Junction;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Sort;
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;
use Blush\Storage\StorageArea;
use Blush\Support\Uuid;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Record::class)]
#[CoversClass(Table::class)]
#[CoversClass(TableRegistry::class)]
#[CoversClass(RecordQuery::class)]
#[CoversClass(Condition::class)]
#[CoversClass(ConditionGroup::class)]
#[CoversClass(Operator::class)]
#[CoversClass(Sort::class)]
#[CoversClass(RecordStores::class)]
#[CoversClass(Uuid::class)]
final class RecordTest extends TestCase
{
	use BootsScratchSite;

	private const string ID = '01900000-0000-7000-8000-000000000001';

	public function testARecordHasAnIdValuesAndABody(): void
	{
		$record = new Record(strtoupper(self::ID), ['title' => 'Notes', 'seo' => ['title' => 'SEO'], 'a.b' => 'dotted'], 'Text.');

		$this->assertSame(self::ID, $record->id, 'Ids are kept lowercase.');
		$this->assertSame('Notes', $record->value('title'));
		$this->assertSame('SEO', $record->value('seo.title'));
		$this->assertSame('dotted', $record->value('a.b'), 'A key with a dot is found as it is first.');
		$this->assertNull($record->value('seo.missing'));
		$this->assertSame(self::ID, $record->value('id'));
		$this->assertSame('Text.', $record->value('body'));

		$changed = $record->with('title', 'Changed')->without('seo')->withBody(null);

		$this->assertSame(['title' => 'Changed', 'a.b' => 'dotted'], $changed->values);
		$this->assertNull($changed->body);
		$this->assertSame('Notes', $record->value('title'), 'Records are immutable.');

		$created = Record::create(new DateTimeImmutable('2026-10-08 12:00:00'), ['title' => 'New']);

		$this->assertTrue(Uuid::isValid($created->id));
		$this->assertSame('7', $created->id[14], 'New ids are version 7.');
	}

	public function testARecordRefusesABadIdOrAReservedKey(): void
	{
		foreach ([static fn (): Record => new Record('nope'), static fn (): Record => new Record(self::ID, ['id' => 'x']), static fn (): Record => new Record(self::ID, ['body' => 'x'])] as $make) {
			try {
				$make();
				$this->fail('Made a record it shouldn\'t.');
			} catch (InvalidRecord) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTablesCheckTheirNamesAreasAndKeys(): void
	{
		$table = new Table('gallery/albums', StorageArea::Data, key: 'slug');

		$this->assertSame('summer-2026', $table->keyOf(new Record(self::ID, ['slug' => 'summer-2026'])));
		$this->assertNull(new Table('notes', StorageArea::Data)->keyOf(new Record(self::ID)));

		$cases = [
			static fn (): Table => new Table('Albums', StorageArea::Data),
			static fn (): Table => new Table('albums/', StorageArea::Data),
			static fn (): Table => new Table('sessions', StorageArea::Sessions),
			static fn (): Table => new Table('albums', StorageArea::Data, key: 'id'),
			static fn (): ?string => $table->keyOf(new Record(self::ID, ['slug' => '.hidden']))
		];

		foreach ($cases as $case) {
			try {
				$case();
				$this->fail('Took a table or key it shouldn\'t.');
			} catch (InvalidRecord) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTheRegistryKeepsOneTableAName(): void
	{
		$tables = new TableRegistry();
		$tables->register(new Table('albums', StorageArea::Data, key: 'slug'));
		$tables->register(new Table('albums', StorageArea::Data, key: 'slug'));

		$this->assertSame(['albums'], array_keys($tables->all()));
		$this->assertSame('slug', $tables->get('albums')?->key);

		$this->expectException(InvalidRecord::class);

		$tables->register(new Table('albums', StorageArea::Data));
	}

	public function testAQueryIsImmutableAndBuildsATree(): void
	{
		$query  = new RecordQuery();
		$built  = $query
			->where('status', '=', 'published')
			->where('year', Operator::GreaterOrEqual, 2020)
			->whereAny(static fn (RecordQuery $q): RecordQuery => $q->where('featured', 'NOT NULL'))
			->orderBy('year', Order::Desc)
			->orderBy('title')
			->paginate(perPage: 10, page: 3);

		$this->assertTrue($query->conditions->isEmpty());
		$this->assertSame(Junction::All, $built->conditions->junction);
		$this->assertCount(3, $built->conditions->conditions);
		$this->assertInstanceOf(ConditionGroup::class, $built->conditions->conditions[2]);
		$this->assertSame(Junction::Any, $built->conditions->conditions[2]->junction);
		$this->assertSame(Operator::NotNull, $built->conditions->conditions[2]->conditions[0]->conditions[0]->operator ?? null, 'Operators are given by name, without regard to case.');
		$this->assertSame(['year', 'title'], array_map(static fn (Sort $sort): string => $sort->key, $built->sorts));
		$this->assertSame([10, 20], [$built->limit, $built->offset]);
	}

	public function testAQueryRefusesWhatItCantRun(): void
	{
		$cases = [
			'no key'               => static fn (): RecordQuery => new RecordQuery()->where('', '=', 1),
			'an empty segment'     => static fn (): RecordQuery => new RecordQuery()->where('seo..title', '=', 1),
			'no such operator'     => static fn (): RecordQuery => new RecordQuery()->where('a', '~', 1),
			'in takes a list'      => static fn (): RecordQuery => new RecordQuery()->where('a', 'in', 'x'),
			'between takes two'    => static fn (): RecordQuery => new RecordQuery()->where('a', 'between', [1]),
			'like takes text'      => static fn (): RecordQuery => new RecordQuery()->where('a', 'like', 1),
			'null takes nothing'   => static fn (): RecordQuery => new RecordQuery()->where('a', 'null', 1),
			'< takes no null'      => static fn (): RecordQuery => new RecordQuery()->where('a', '<', null),
			'= takes no list'      => static fn (): RecordQuery => new RecordQuery()->where('a', '=', ['x']),
			'a negative limit'     => static fn (): RecordQuery => new RecordQuery()->limit(-1),
			'a negative offset'    => static fn (): RecordQuery => new RecordQuery()->offset(-1),
			'page zero'            => static fn (): RecordQuery => new RecordQuery()->paginate(10, 0),
			'no key to order by'   => static fn (): RecordQuery => new RecordQuery()->orderBy(''),
			'running it unbound'   => static fn (): mixed => new RecordQuery()->get()
		];

		foreach ($cases as $name => $case) {
			try {
				$case();
				$this->fail("Took {$name}.");
			} catch (InvalidRecordQuery) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testNamedIdsAreSteady(): void
	{
		$this->assertSame(Uuid::fromName('roles/editor'), Uuid::fromName('roles/editor'));
		$this->assertNotSame(Uuid::fromName('roles/editor'), Uuid::fromName('roles/author'));
		$this->assertTrue(Uuid::isValid(Uuid::fromName('roles/editor')));
		$this->assertSame('5', Uuid::fromName('roles/editor')[14], 'Named ids are version 5.');
	}

	public function testStoresComeFromEachAreasDriver(): void
	{
		$stores = $this->scratchApplication()->container()->make(RecordStores::class);
		$table  = new Table('albums', StorageArea::Data, key: 'slug');

		$this->assertInstanceOf(FileRecordStore::class, $stores->store($table));

		$stores->store($table)->save($table, new Record(self::ID, ['slug' => 'summer', 'title' => 'Summer']));

		$this->assertSame('Summer', $stores->query($table)->where('slug', '=', 'summer')->first()?->values['title']);
		$this->assertSame(1, $stores->query($table)->count());
		$this->assertFileExists($this->temporaryDirectory() . '/user/data/albums/summer.json', 'A folder table in its area\'s root, a file a record named by its key.');
	}
}
