<?php

/**
 * Record store conformance.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage\Conformance;

use Closure;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Subquery;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * What every `RecordStore` answers alike (D-643): the same records, the
 * same queries, the same results. A driver extends it with `store()`;
 * one that fails a case isn't done.
 *
 * The fixtures are five albums, added in order (their ids rise), with
 * values chosen to pin the edges: a year kept as text, a missing key, a
 * null, a float equal to an integer, mixed case, an accent, and a `%`.
 */
abstract class RecordStoreConformance extends TestCase
{
	protected const string ALPHA   = '01900000-0000-7000-8000-000000000001';
	protected const string BETA    = '01900000-0000-7000-8000-000000000002';
	protected const string GAMMA   = '01900000-0000-7000-8000-000000000003';
	protected const string DELTA   = '01900000-0000-7000-8000-000000000004';
	protected const string EPSILON = '01900000-0000-7000-8000-000000000005';

	/**
	 * Returns a new, empty store.
	 */
	abstract protected function store(): RecordStore;

	protected function table(): Table
	{
		return new Table('albums', StorageArea::Data, key: 'slug', fields: ['slug', 'year']);
	}

	/**
	 * The store, with the five albums added.
	 */
	protected function seeded(): RecordStore
	{
		$store = $this->store();
		$table = $this->table();

		$store->save($table, new Record(self::ALPHA, ['slug' => 'alpha', 'title' => 'Alpha', 'year' => 2019, 'rating' => 4.5, 'tags' => ['art', 'travel'], 'status' => 'published', 'featured' => true, 'seo' => ['title' => 'A']]));
		$store->save($table, new Record(self::BETA, ['slug' => 'beta', 'title' => 'beta', 'year' => 2021, 'rating' => 3, 'tags' => ['art'], 'status' => 'draft', 'featured' => false]));
		$store->save($table, new Record(self::GAMMA, ['slug' => 'gamma', 'title' => 'Gamma', 'year' => '2020', 'rating' => null, 'tags' => [], 'status' => 'published', 'featured' => false], 'Hello world'));
		$store->save($table, new Record(self::DELTA, ['slug' => 'delta', 'title' => 'Delta 100%', 'year' => 2022, 'rating' => 5, 'tags' => ['travel'], 'status' => 'published']));
		$store->save($table, new Record(self::EPSILON, ['slug' => 'epsilon', 'title' => 'Épsilon', 'year' => 2018, 'rating' => 2.5, 'tags' => ['art', 'food'], 'status' => 'archived', 'featured' => true, 'seo' => ['title' => 'E']]));

		return $store;
	}

	/**
	 * The slugs a query finds.
	 *
	 * @param  Closure(RecordQuery): RecordQuery $build
	 * @return list<mixed>
	 */
	protected function slugs(RecordStore $store, Closure $build): array
	{
		return array_map(static fn (Record $record): mixed => $record->fields['slug'] ?? null, $store->select($this->table(), $build(new RecordQuery()))->records);
	}

	public function testFindsSavesAndDeletes(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		$this->assertSame('Gamma', $store->find($table, self::GAMMA)?->fields['title']);
		$this->assertSame('Hello world', $store->find($table, strtoupper(self::GAMMA))?->content, 'Ids are found in either case.');
		$this->assertNull($store->find($table, '01900000-0000-7000-8000-000000000099'));
		$this->assertSame(self::DELTA, $store->findByKey($table, 'delta')?->id);
		$this->assertNull($store->findByKey($table, 'zeta'));
		$this->assertSame([self::EPSILON, self::ALPHA], array_keys($store->findMany($table, [self::EPSILON, '01900000-0000-7000-8000-000000000099', self::ALPHA])), 'In the order asked; missing ids left out.');

		$store->save($table, new Record(self::BETA, ['slug' => 'beta', 'title' => 'Beta']));

		$this->assertSame(['slug' => 'beta', 'title' => 'Beta'], $store->find($table, self::BETA)?->fields, 'Saving replaces the record whole.');
		$this->assertSame(['alpha', 'beta', 'gamma', 'delta', 'epsilon'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q), 'It keeps its place.');

		$store->delete($table, self::GAMMA);
		$store->delete($table, self::GAMMA);

		$this->assertNull($store->find($table, self::GAMMA));
		$this->assertSame(['alpha', 'beta', 'delta', 'epsilon'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q));
	}

	public function testAKeyChangesWithItsRecord(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		$store->save($table, new Record(self::BETA, ['slug' => 'bravo', 'title' => 'Bravo']));

		$this->assertNull($store->findByKey($table, 'beta'));
		$this->assertSame(self::BETA, $store->findByKey($table, 'bravo')?->id);
		$this->assertSame(5, $store->count($table, new RecordQuery()));
	}

	public function testRefusesAKeyAnotherRecordHas(): void
	{
		$store = $this->seeded();

		$this->expectException(InvalidRecord::class);

		$store->save($this->table(), new Record('01900000-0000-7000-8000-000000000006', ['slug' => 'alpha']));
	}

	public function testRefusesAMissingOrMalformedKey(): void
	{
		$store = $this->store();

		foreach ([[], ['slug' => ''], ['slug' => '../up'], ['slug' => 7]] as $values) {
			try {
				$store->save($this->table(), new Record(self::ALPHA, $values));
				$this->fail(sprintf('Saved a record with %s.', json_encode($values)));
			} catch (InvalidRecord) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testATableWithoutAKeyIsKnownByIds(): void
	{
		$store = $this->store();
		$table = new Table('notes', StorageArea::Data);

		$store->save($table, new Record(self::ALPHA, ['text' => 'One']));
		$store->save($table, new Record(self::BETA, ['text' => 'Two']));

		$this->assertSame('Two', $store->find($table, self::BETA)?->fields['text']);
		$this->assertSame(2, $store->count($table, new RecordQuery()));

		$this->expectException(InvalidRecord::class);

		$store->findByKey($table, 'One');
	}

	public function testRecordsComeInTheOrderTheyWereAdded(): void
	{
		$this->assertSame(['alpha', 'beta', 'gamma', 'delta', 'epsilon'], $this->slugs($this->seeded(), static fn (RecordQuery $q): RecordQuery => $q));
	}

	public function testComparesStrictly(): void
	{
		$store = $this->seeded();
		$cases = [
			'= text'                       => [['status', '=', 'published'], ['alpha', 'gamma', 'delta']],
			'= a number isn\'t its text'   => [['year', '=', 2020], []],
			'= text isn\'t its number'     => [['year', '=', '2020'], ['gamma']],
			'= 3.0 is 3'                   => [['rating', '=', 3.0], ['beta']],
			'= is case-sensitive'          => [['title', '=', 'Beta'], []],
			'= true'                       => [['featured', '=', true], ['alpha', 'epsilon']],
			'= null matches missing'       => [['featured', '=', null], ['delta']],
			'!= matches missing and null'  => [['featured', '!=', true], ['beta', 'gamma', 'delta']],
			'< numbers only'               => [['year', '<', 2020], ['alpha', 'epsilon']],
			'<= numbers only'              => [['year', '<=', 2019], ['alpha', 'epsilon']],
			'> text byte by byte'          => [['title', '>', 'Delta'], ['beta', 'gamma', 'delta', 'epsilon']],
			'>= numbers only'              => [['year', '>=', 2021], ['beta', 'delta']],
			'> null never matches'         => [['rating', '>', 0], ['alpha', 'beta', 'delta', 'epsilon']],
			'between, inclusive'           => [['year', 'between', [2019, 2021]], ['alpha', 'beta']],
			'between text'                 => [['year', 'between', ['2000', '2099']], ['gamma']],
			'in'                           => [['status', 'in', ['draft', 'archived']], ['beta', 'epsilon']],
			'in strictly'                  => [['year', 'in', [2020, 2021]], ['beta']],
			'not in matches missing'       => [['featured', 'not in', [true]], ['beta', 'gamma', 'delta']],
			'like, without regard to case' => [['title', 'like', 'g%'], ['gamma']],
			'like one character'           => [['title', 'like', '_eta'], ['beta']],
			'like escaped'                 => [['title', 'like', '% 100\%'], ['delta']],
			'like accents'                 => [['title', 'like', 'é%'], ['epsilon']],
			'intersects'                   => [['tags', 'intersects', ['food', 'travel']], ['alpha', 'delta', 'epsilon']],
			'intersects nothing'           => [['tags', 'intersects', []], []],
			'like text only'               => [['year', 'like', '20%'], ['gamma']],
			'contains'                     => [['tags', 'contains', 'art'], ['alpha', 'beta', 'epsilon']],
			'null'                         => [['rating', 'null'], ['gamma']],
			'not null'                     => [['featured', 'not null'], ['alpha', 'beta', 'gamma', 'epsilon']],
			'dotted keys'                  => [['seo.title', '=', 'E'], ['epsilon']],
			'a missing nested key'         => [['seo.title', 'null'], ['beta', 'gamma', 'delta']],
			'content'                         => [['content', 'like', '%world'], ['gamma']],
			'id'                           => [['id', '=', self::GAMMA], ['gamma']]
		];

		foreach ($cases as $name => [$where, $expected]) {
			$this->assertSame($expected, $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q->where(...$where)), $name);
		}
	}

	public function testGroupsNest(): void
	{
		$store = $this->seeded();

		$this->assertSame(['alpha', 'delta'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q
			->where('status', '!=', 'archived')
			->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('featured', '=', true),
				static fn (RecordQuery $q): RecordQuery => $q->where('year', '>=', 2022)
			)));

		$this->assertSame(['alpha', 'beta', 'delta'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q->whereAny(
			static fn (RecordQuery $q): RecordQuery => $q->where('status', '=', 'published')->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('tags', 'contains', 'travel'),
				static fn (RecordQuery $q): RecordQuery => $q->where('rating', '>', 4)
			),
			static fn (RecordQuery $q): RecordQuery => $q->where('status', '=', 'draft')
		)));

		$this->assertSame([], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q->whereAny()), 'No alternatives match nothing.');
	}

	public function testOrders(): void
	{
		$store = $this->seeded();
		$cases = [
			'numbers, then text'            => [[['year', Order::Asc]], ['epsilon', 'alpha', 'beta', 'delta', 'gamma']],
			'null last, descending too'     => [[['rating', Order::Desc]], ['delta', 'alpha', 'beta', 'epsilon', 'gamma']],
			'null last, ascending'          => [[['rating', Order::Asc]], ['epsilon', 'beta', 'alpha', 'delta', 'gamma']],
			'several keys'                  => [[['status', Order::Asc], ['title', Order::Desc]], ['epsilon', 'beta', 'gamma', 'delta', 'alpha']],
			'false, true, then missing'     => [[['featured', Order::Asc]], ['beta', 'gamma', 'alpha', 'epsilon', 'delta']],
			'text without regard to case'   => [[['title', Order::Asc]], ['alpha', 'beta', 'delta', 'gamma', 'epsilon']],
			'ties keep the order added'     => [[['status', Order::Desc]], ['alpha', 'gamma', 'delta', 'beta', 'epsilon']]
		];

		foreach ($cases as $name => [$sorts, $expected]) {
			$this->assertSame($expected, $this->slugs($store, static function (RecordQuery $q) use ($sorts): RecordQuery {
				foreach ($sorts as [$key, $order]) {
					$q = $q->orderBy($key, $order);
				}

				return $q;
			}), $name);
		}
	}

	public function testLimitsOffsetsAndPages(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		$page = $store->select($table, new RecordQuery()->orderBy('slug')->limit(2)->offset(1));

		$this->assertSame(['beta', 'delta'], array_map(static fn (Record $record): mixed => $record->fields['slug'], $page->records));
		$this->assertSame(5, $page->total, 'The total is before the limit and offset.');

		$last = $store->select($table, new RecordQuery()->orderBy('slug')->paginate(perPage: 2, page: 3));

		$this->assertSame(['gamma'], array_map(static fn (Record $record): mixed => $record->fields['slug'], $last->records));
		$this->assertSame([], $store->select($table, new RecordQuery()->paginate(perPage: 2, page: 4))->records);
		$this->assertSame([], $store->select($table, new RecordQuery()->limit(0))->records);
		$this->assertSame(3, $store->count($table, new RecordQuery()->where('status', '=', 'published')->limit(1)), 'A count ignores the limit.');
	}

	public function testCountsByValue(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		$this->assertSame([['value' => 'art', 'count' => 3], ['value' => 'food', 'count' => 1], ['value' => 'travel', 'count' => 2]], $store->countBy($table, new RecordQuery(), 'tags'), 'A list counts each value.');
		$this->assertSame([['value' => 'archived', 'count' => 1], ['value' => 'draft', 'count' => 1], ['value' => 'published', 'count' => 3]], $store->countBy($table, new RecordQuery(), 'status'));
		$this->assertSame([['value' => false, 'count' => 2], ['value' => true, 'count' => 2]], $store->countBy($table, new RecordQuery(), 'featured'), 'Missing values aren\'t counted.');
		$this->assertSame([['value' => 'travel', 'count' => 1]], $store->countBy($table, new RecordQuery()->where('status', '=', 'published')->where('featured', '=', null), 'tags'));
	}

	public function testAggregates(): void
	{
		$store = $this->seeded();
		$table = $this->table();
		$all   = new RecordQuery();

		$this->assertSame(2.5, $store->aggregate($table, $all, Aggregate::Min, 'rating'));
		$this->assertSame(5, $store->aggregate($table, $all, Aggregate::Max, 'rating'));
		$this->assertSame(15.0, $store->aggregate($table, $all, Aggregate::Sum, 'rating'));
		$this->assertSame(3.75, $store->aggregate($table, $all, Aggregate::Avg, 'rating'));
		$this->assertSame(8080, $store->aggregate($table, $all, Aggregate::Sum, 'year'), 'Text isn\'t summed.');
		$this->assertSame(2018, $store->aggregate($table, $all, Aggregate::Min, 'year'));
		$this->assertSame('2020', $store->aggregate($table, $all, Aggregate::Max, 'year'), 'Text sorts after numbers.');
		$this->assertSame('Alpha', $store->aggregate($table, $all, Aggregate::Min, 'title'));
		$this->assertSame('Épsilon', $store->aggregate($table, $all, Aggregate::Max, 'title'));
		$this->assertSame(3, $store->aggregate($table, $all->where('status', '=', 'draft'), Aggregate::Sum, 'rating'));

		$none = $all->where('status', '=', 'gone');

		$this->assertNull($store->aggregate($table, $none, Aggregate::Min, 'rating'));
		$this->assertNull($store->aggregate($table, $none, Aggregate::Avg, 'rating'));
		$this->assertSame(0, $store->aggregate($table, $none, Aggregate::Sum, 'rating'));
	}

	public function testVersionsRefuseStaleWrites(): void
	{
		$store = $this->seeded();
		$table = $this->table();
		$alpha = $store->find($table, self::ALPHA) ?? throw new RuntimeException('No alpha.');

		$this->assertNotNull($alpha->version, 'A record read carries its version.');

		$saved = $store->save($table, $alpha->with('title', 'Alpha 2'), $alpha->version);

		$this->assertNotSame($alpha->version, $saved->version, 'A save changes it.');
		$this->assertSame($saved->version, $store->find($table, self::ALPHA)?->version);
		$this->assertSame($saved->version, $store->select($table, new RecordQuery()->where('id', '=', self::ALPHA))->first()?->version, 'Queries carry it too.');

		$conflicts = [
			'a stale save'   => static fn () => $store->save($table, $alpha->with('title', 'Alpha 3'), $alpha->version),
			'a stale delete' => static fn () => $store->delete($table, self::ALPHA, $alpha->version)
		];

		foreach ($conflicts as $name => $write) {
			try {
				$write();
				$this->fail("Allowed {$name}.");
			} catch (RecordConflict) {
				$this->addToAssertionCount(1);
			}
		}

		$this->assertSame('Alpha 2', $store->find($table, self::ALPHA)?->fields['title'], 'Nothing was written.');

		$store->delete($table, self::ALPHA, $saved->version);

		$this->assertNull($store->find($table, self::ALPHA));

		try {
			$store->save($table, $saved, $saved->version);
			$this->fail('Saved over a record that\'s gone.');
		} catch (RecordConflict) {
			$this->addToAssertionCount(1);
		}

		$store->save($table, $saved);

		$this->assertNotNull($store->find($table, self::ALPHA), 'Without a version, a save writes whatever is there.');
	}

	public function testSubqueries(): void
	{
		$store = $this->seeded();
		$picks = new Table('picks', StorageArea::Data);

		$store->save($picks, new Record('01900000-0000-7000-8000-000000000101', ['album' => 'gamma']));
		$store->save($picks, new Record('01900000-0000-7000-8000-000000000102', ['album' => 'alpha']));

		$cases = [
			'in the same table'     => [['year', 'in', new Subquery(new RecordQuery()->where('status', '=', 'draft'), 'year')], ['beta']],
			'not in'                => [['status', 'not in', new Subquery(new RecordQuery()->where('featured', '=', true), 'status')], ['beta']],
			'its order and limit'   => [['slug', 'in', new Subquery(new RecordQuery()->orderBy('rating', Order::Desc)->limit(1), 'slug')], ['delta']],
			'in another table'      => [['slug', 'in', new Subquery(new RecordQuery(), 'album', $picks)], ['alpha', 'gamma']],
			'nothing found'         => [['slug', 'in', new Subquery(new RecordQuery()->where('status', '=', 'gone'), 'slug')], []]
		];

		foreach ($cases as $name => [$where, $expected]) {
			$this->assertSame($expected, $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q->where(...$where)), $name);
		}
	}

	public function testRefsFilterAndLoad(): void
	{
		$store = $this->seeded();
		$table = $this->table();
		$refs  = Ref::table($table->area);

		$store->save($refs, new Ref(self::ALPHA, 'similar', self::GAMMA, 0)->record());
		$store->save($refs, new Ref(self::ALPHA, 'similar', self::BETA, 1)->record());
		$store->save($refs, new Ref(self::DELTA, 'similar', self::ALPHA, 0)->record());
		$store->save($refs, new Ref(self::DELTA, 'sequel', self::EPSILON, 0)->record());

		$cases = [
			'refers to'             => [static fn (RecordQuery $q): RecordQuery => $q->whereRelated('similar', [self::GAMMA]), ['alpha']],
			'refers to any'         => [static fn (RecordQuery $q): RecordQuery => $q->whereRelated('similar', [self::GAMMA, self::ALPHA]), ['alpha', 'delta']],
			'by relation'           => [static fn (RecordQuery $q): RecordQuery => $q->whereRelated('sequel', [self::ALPHA]), []],
			'referred to'           => [static fn (RecordQuery $q): RecordQuery => $q->whereRelated('similar', [self::ALPHA], inverse: true), ['beta', 'gamma']],
			'a subquery of targets' => [static fn (RecordQuery $q): RecordQuery => $q->whereRelated('similar', new Subquery(new RecordQuery()->where('status', '=', 'draft'), 'id')), ['alpha']],
			'in a group'            => [static fn (RecordQuery $q): RecordQuery => $q->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->whereRelated('sequel', [self::EPSILON]),
				static fn (RecordQuery $q): RecordQuery => $q->where('slug', '=', 'beta')
			), ['beta', 'delta']]
		];

		foreach ($cases as $name => [$build, $expected]) {
			$this->assertSame($expected, $this->slugs($store, $build), $name);
		}

		$result = new RecordQuery(store: $store, table: $table)->where('slug', 'in', ['alpha', 'beta', 'delta'])->with('similar')->get();

		$this->assertSame([self::GAMMA, self::BETA], $result->refs(self::ALPHA, 'similar'), 'Loaded in order.');
		$this->assertSame([self::ALPHA], $result->refs(strtoupper(self::DELTA), 'similar'));
		$this->assertSame([], $result->refs(self::DELTA, 'sequel'), 'Only the relations asked for.');
		$this->assertSame([], $result->refs(self::BETA, 'similar'));
	}

	public function testAFailedTransactionPutsBackItsWrites(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		try {
			$store->transaction(static function () use ($store, $table): void {
				$store->save($table, new Record(self::ALPHA, ['slug' => 'alpha', 'title' => 'Changed']));
				$store->delete($table, self::BETA);
				$store->save($table, new Record('01900000-0000-7000-8000-000000000006', ['slug' => 'zeta']));

				throw new RuntimeException('Doesn\'t fit.');
			});
		} catch (RuntimeException $error) {
			$this->assertSame('Doesn\'t fit.', $error->getMessage());
		}

		$this->assertSame('Alpha', $store->find($table, self::ALPHA)?->fields['title']);
		$this->assertSame(['alpha', 'beta', 'gamma', 'delta', 'epsilon'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q));
	}

	public function testAFailedInnerTransactionPutsBackOnlyItsOwn(): void
	{
		$store = $this->seeded();
		$table = $this->table();

		$result = $store->transaction(static function () use ($store, $table): string {
			$store->delete($table, self::ALPHA);

			try {
				$store->transaction(static function () use ($store, $table): void {
					$store->delete($table, self::BETA);

					throw new RuntimeException('Not this one.');
				});
			} catch (RuntimeException) {
			}

			return 'done';
		});

		$this->assertSame('done', $result);
		$this->assertSame(['beta', 'gamma', 'delta', 'epsilon'], $this->slugs($store, static fn (RecordQuery $q): RecordQuery => $q));

		try {
			$store->transaction(static function () use ($store, $table): void {
				$store->transaction(static fn () => $store->delete($table, self::GAMMA));

				throw new RuntimeException('Neither.');
			});
		} catch (RuntimeException) {
		}

		$this->assertNotNull($store->find($table, self::GAMMA), 'An outer failure puts back its inner ones\' writes.');
	}
}
