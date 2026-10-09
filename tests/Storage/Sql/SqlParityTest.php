<?php

/**
 * SQL parity test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\Subquery;
use Blush\Storage\Record\Table;
use Blush\Storage\Sql\SqlCompiler;
use Blush\Storage\Sql\SqliteDialect;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Storage\StorageArea;

/**
 * Every operator, against every kind of value, over records whose
 * values are every kind too (numbers as text, `true` beside `1`, `1.0`
 * beside `1`, text in other cases and scripts, lists, maps, a field
 * named with a dot), answered by SQLite as `ArrayEvaluator` answers it:
 * the same records in the same order, and the same counts and
 * aggregates.
 */
#[CoversClass(SqliteRecordStore::class)]
#[CoversClass(SqlCompiler::class)]
#[CoversClass(SqliteDialect::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class SqlParityTest extends TestCase
{
	/**
	 * Values the records hold, and queries compare against.
	 */
	private const array VALUES = [null, true, false, 0, 1, 1.0, 2.5, -3, '1', '', 'apple', 'Apple', 'ÉMILE', 'émile', 'zebra', 'a_b', '100%', '2026-01-01T00:00:00Z'];

	private Table $table;

	/**
	 * The same records in a table that declares their values, so they're
	 * read from columns.
	 */
	private Table $declared;

	private RecordStore $array;

	private RecordStore $sqlite;

	protected function setUp(): void
	{
		$this->table    = new Table('things', StorageArea::Data);
		$this->declared = new Table('declared', StorageArea::Data, fields: ['kind', 'score', 'tags', 'seo', 'a']);
		$this->array  = new ArrayRecordStore();
		$this->sqlite = SqliteRecordStore::open(':memory:');

		$values = self::VALUES;
		$count  = count($values);

		foreach ($values as $index => $value) {
			$fields = [
				'kind'  => $value,
				'score' => $values[($index * 7 + 3) % $count],
				'tags'  => [$values[($index + 1) % $count], $values[($index + 5) % $count]],
				'seo'   => ['title' => $values[($index + 2) % $count]],
				'a.b'   => $values[($index + 4) % $count]
			];

			// Some records leave values out altogether.
			if ($index % 4 === 0) {
				unset($fields['score'], $fields['seo']);
			}

			$record = new Record(sprintf('01900000-0000-7000-8000-%012d', $index + 1), $fields, $index % 3 === 0 ? null : "Body {$index} Émile");

			foreach ([$this->table, $this->declared] as $table) {
				$this->array->save($table, $record);
				$this->sqlite->save($table, $record);
			}
		}
	}

	/**
	 * Returns the ids a query finds, and its total, from a store.
	 *
	 * @return array{list<string>, int}
	 */
	private function found(RecordStore $store, RecordQuery $query, Table $table): array
	{
		$result = $store->select($table, $query);

		return [array_map(static fn (Record $record): string => substr($record->id, -4), $result->records), $result->total];
	}

	/**
	 * Asserts both stores answer a query alike.
	 */
	private function same(RecordQuery $query, string $what): void
	{
		foreach ([$this->table, $this->declared] as $table) {
			$this->assertSame($this->found($this->array, $query, $table), $this->found($this->sqlite, $query, $table), "{$what} ({$table->name})");
		}
	}

	public function testEveryComparisonAnswersAlike(): void
	{
		$keys = ['kind', 'score', 'seo.title', 'kind.x', 'a.b', 'content', 'id', 'missing'];

		foreach ($keys as $key) {
			foreach (self::VALUES as $value) {
				$shown = var_export($value, true);

				foreach ([Operator::Equal, Operator::NotEqual] as $operator) {
					$this->same(new RecordQuery()->where($key, $operator, $value), "{$key} {$operator->value} {$shown}");
				}

				if (is_int($value) || is_float($value) || is_string($value)) {
					foreach ([Operator::Less, Operator::LessOrEqual, Operator::Greater, Operator::GreaterOrEqual] as $operator) {
						$this->same(new RecordQuery()->where($key, $operator, $value), "{$key} {$operator->value} {$shown}");
					}
				}
			}

			foreach ([[1, '1', true], [null], [1.0, 'apple', false], [], ['ÉMILE', -3, 2.5]] as $list) {
				$shown = json_encode($list);

				$this->same(new RecordQuery()->where($key, 'in', $list), "{$key} in {$shown}");
				$this->same(new RecordQuery()->where($key, 'not in', $list), "{$key} not in {$shown}");
			}

			foreach ([[0, 2], ['a', 'z'], [1, 'z'], ['', 'apple']] as $range) {
				$this->same(new RecordQuery()->where($key, 'between', $range), "{$key} between " . json_encode($range));
			}

			foreach (['%mile%', '%MILE%', 'app%', '_pple', 'a\\_b', '100\\%', '%', '', 'body%', '%é%'] as $pattern) {
				$this->same(new RecordQuery()->where($key, 'like', $pattern), "{$key} like {$pattern}");
			}

			$this->same(new RecordQuery()->where($key, 'null'), "{$key} null");
			$this->same(new RecordQuery()->where($key, 'not null'), "{$key} not null");
		}
	}

	public function testListsAnswerAlike(): void
	{
		foreach (self::VALUES as $value) {
			$this->same(new RecordQuery()->where('tags', 'contains', $value), 'tags contains ' . var_export($value, true));
		}

		foreach ([['apple', 1], [true], [null, 2.5], ['Apple'], []] as $list) {
			$this->same(new RecordQuery()->where('tags', 'intersects', $list), 'tags intersects ' . json_encode($list));
			$this->same(new RecordQuery()->where('kind', 'intersects', $list), 'kind intersects ' . json_encode($list));
			$this->same(new RecordQuery()->where('seo.title', 'intersects', $list), 'seo.title intersects ' . json_encode($list));
			$this->same(new RecordQuery()->where('tags', 'intersects', $list)->orderBy('kind')->limit(2)->offset(1), 'a page of tags intersects ' . json_encode($list));
			$this->same(new RecordQuery()->where('tags', 'intersects', $list)->limit(2)->offset(50), 'a page past the end of tags intersects ' . json_encode($list));
		}
	}

	public function testOrdersAnswerAlike(): void
	{
		foreach (['kind', 'score', 'seo.title', 'a.b', 'tags', 'tags.0', 'content', 'missing'] as $key) {
			foreach ([Order::Asc, Order::Desc] as $order) {
				$this->same(new RecordQuery()->orderBy($key, $order), "order by {$key} {$order->value}");
				$this->same(new RecordQuery()->orderBy($key, $order)->orderBy('kind', Order::Desc)->limit(5)->offset(2), "order by {$key} {$order->value}, kind, paged");
			}
		}
	}

	public function testGroupsAndSubqueriesAnswerAlike(): void
	{
		$query = new RecordQuery()
			->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('kind', '=', 1),
				static fn (RecordQuery $q): RecordQuery => $q->where('score', 'like', '%e%')->where('content', 'not null'),
				static fn (RecordQuery $q): RecordQuery => $q->whereAny()
			)
			->where('id', 'not in', new Subquery(new RecordQuery()->where('kind', 'null')->orderBy('score')->limit(2), 'id'));

		$this->same($query, 'nested groups and a subquery');
		$this->same(new RecordQuery()->where('kind', 'in', new Subquery(new RecordQuery()->where('kind', '!=', null), 'score')), 'a subquery of another key');
	}

	public function testCountsAndAggregatesAnswerAlike(): void
	{
		foreach ([$this->table, $this->declared] as $table) {
			foreach (['kind', 'score', 'tags', 'seo.title', 'a.b', 'content', 'missing'] as $key) {
				$this->assertSame($this->array->countBy($table, new RecordQuery(), $key), $this->sqlite->countBy($table, new RecordQuery(), $key), "count by {$key}");

				foreach (Aggregate::cases() as $function) {
					$this->assertSame($this->array->aggregate($table, new RecordQuery(), $function, $key), $this->sqlite->aggregate($table, new RecordQuery(), $function, $key), "{$function->value} of {$key}");
				}
			}
		}

		$this->assertSame($this->array->count($this->table, new RecordQuery()->where('kind', 'not null')), $this->sqlite->count($this->table, new RecordQuery()->where('kind', 'not null')));
	}
}
