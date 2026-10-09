<?php

/**
 * Record query.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;

/**
 * Which records to find, built fluently (D-643). It's immutable: each
 * call returns a copy, and the query itself is what each driver
 * compiles (array work over files, SQL for a database).
 *
 *     $stores->query($albums)
 *         ->where('status', '=', 'published')
 *         ->whereAny(
 *             static fn (RecordQuery $q): RecordQuery => $q->where('year', '>=', 2020),
 *             static fn (RecordQuery $q): RecordQuery => $q->where('featured', '=', true)
 *         )
 *         ->orderBy('title')
 *         ->paginate(perPage: 20, page: 2)
 *         ->get();
 *
 * Keys are fields' keys, dotted to reach into nested fields, or `id` or
 * `content`; `Operator` says how each compares, and `in` and `not in`
 * take a `Subquery`. Every condition must hold; `whereAny()` takes
 * alternatives, each a group of its own, and groups nest. Ties in the
 * order, and a query with none, keep the order records were added in;
 * text sorts without regard to case (D-649).
 *
 * Refs (D-649): `whereRelated()` keeps records that refer, through a
 * relation, to any of some ids or a subquery's (or, inverse, that they
 * refer to), and `with()` loads what each found record refers to into
 * the result.
 *
 * A query from a store (`RecordStores::query()`) runs itself with
 * `get()`, `first()`, `count()`, `countBy()`, and the aggregates.
 */
final readonly class RecordQuery
{
	/**
	 * @param list<Sort>   $sorts
	 * @param list<string> $with    Relations whose refs a run loads.
	 * @param bool         $content Whether the records found carry their content.
	 */
	public function __construct(
		public ConditionGroup $conditions = new ConditionGroup(),
		public array $sorts = [],
		public ?int $limit = null,
		public int $offset = 0,
		public array $with = [],
		public bool $content = true,
		private ?RecordStore $store = null,
		private ?Table $table = null
	) {}

	/**
	 * Adds a condition.
	 *
	 * @throws InvalidRecordQuery When the key is empty or the value doesn't fit the operator.
	 */
	#[\NoDiscard]
	public function where(string $key, Operator|string $operator, mixed $value = null): self
	{
		$operator = $operator instanceof Operator ? $operator : (Operator::tryFrom(strtolower($operator)) ?? throw new InvalidRecordQuery(sprintf(
			'"%s" isn\'t an operator; use %s.',
			$operator,
			implode(', ', array_map(static fn (Operator $case): string => "\"{$case->value}\"", Operator::cases()))
		)));

		return clone($this, ['conditions' => $this->conditions->with(new Condition($key, $operator, $value))]);
	}

	/**
	 * Adds alternatives, at least one of which must hold. Each is built
	 * from an empty query, whose conditions (all of which must hold) are
	 * the alternative.
	 *
	 * @param Closure(self): self ...$alternatives
	 */
	#[\NoDiscard]
	public function whereAny(Closure ...$alternatives): self
	{
		$group = new ConditionGroup(Junction::Any);

		foreach ($alternatives as $alternative) {
			$group = $group->with($alternative(new self())->conditions);
		}

		return clone($this, ['conditions' => $this->conditions->with($group)]);
	}

	/**
	 * Keeps records that refer, through a relation, to any of the
	 * targets (ids, or a subquery of them); inverse, records any of the
	 * targets refer to.
	 *
	 * @param  list<string>|Subquery $targets
	 * @throws InvalidRecordQuery
	 */
	#[\NoDiscard]
	public function whereRelated(string $relation, array|Subquery $targets, bool $inverse = false): self
	{
		return clone($this, ['conditions' => $this->conditions->with(new Related($relation, $targets, $inverse))]);
	}

	/**
	 * Loads, when the query runs itself, what each record found refers
	 * to through these relations (`RecordResult::refs()`).
	 *
	 * @throws InvalidRecordQuery When a relation is empty.
	 */
	#[\NoDiscard]
	public function with(string ...$relations): self
	{
		if (in_array('', $relations, true)) {
			throw new InvalidRecordQuery('Name the relations to load.');
		}

		return clone($this, ['with' => array_values(array_unique([...$this->with, ...$relations]))]);
	}

	/**
	 * Leaves the records found without their content, which a store
	 * keeping large text need not read (a listing of entries, say).
	 */
	#[\NoDiscard]
	public function withoutContent(): self
	{
		return clone($this, ['content' => false]);
	}

	/**
	 * Adds a key to order by, after any already given.
	 *
	 * @throws InvalidRecordQuery When the key is empty.
	 */
	#[\NoDiscard]
	public function orderBy(string $key, Order $order = Order::Asc): self
	{
		return clone($this, ['sorts' => [...$this->sorts, new Sort($key, $order)]]);
	}

	/**
	 * Limits how many records are found; `null` for all.
	 *
	 * @throws InvalidRecordQuery For a negative limit.
	 */
	#[\NoDiscard]
	public function limit(?int $limit): self
	{
		if ($limit !== null && $limit < 0) {
			throw new InvalidRecordQuery('A limit can\'t be negative.');
		}

		return clone($this, ['limit' => $limit]);
	}

	/**
	 * Skips the first records found.
	 *
	 * @throws InvalidRecordQuery For a negative offset.
	 */
	#[\NoDiscard]
	public function offset(int $offset): self
	{
		if ($offset < 0) {
			throw new InvalidRecordQuery('An offset can\'t be negative.');
		}

		return clone($this, ['offset' => $offset]);
	}

	/**
	 * Limits the query to one page of records.
	 *
	 * @throws InvalidRecordQuery When the page or its size is less than one.
	 */
	#[\NoDiscard]
	public function paginate(int $perPage, int $page = 1): self
	{
		if ($perPage < 1 || $page < 1) {
			throw new InvalidRecordQuery('Pages and their sizes start at 1.');
		}

		return $this->limit($perPage)->offset(($page - 1) * $perPage);
	}

	/**
	 * Returns the records found, with the refs `with()` asked for.
	 *
	 * @throws RecordException
	 */
	public function get(): RecordResult
	{
		[$store, $table] = $this->runner();

		$result = $store->select($table, $this);

		if ($this->with === []) {
			return $result;
		}

		return $result->withRefs(Refs::group($store, $table, array_map(static fn (Record $record): string => $record->id, $result->records), $this->with));
	}

	/**
	 * Returns the first record found, or `null`.
	 *
	 * @throws RecordException
	 */
	public function first(): ?Record
	{
		return $this->limit(1)->get()->first();
	}

	/**
	 * Returns how many records match.
	 *
	 * @throws RecordException
	 */
	public function count(): int
	{
		[$store, $table] = $this->runner();

		return $store->count($table, $this);
	}

	/**
	 * Returns how many matching records have each value of a key.
	 *
	 * @return list<array{value: bool|int|float|string, count: int}>
	 * @throws RecordException
	 */
	public function countBy(string $key): array
	{
		[$store, $table] = $this->runner();

		return $store->countBy($table, $this, $key);
	}

	/**
	 * Returns the least value of a key among the matching records.
	 *
	 * @throws RecordException
	 */
	public function min(string $key): int|float|string|bool|null
	{
		return $this->aggregate(Aggregate::Min, $key);
	}

	/**
	 * Returns the greatest value of a key among the matching records.
	 *
	 * @throws RecordException
	 */
	public function max(string $key): int|float|string|bool|null
	{
		return $this->aggregate(Aggregate::Max, $key);
	}

	/**
	 * Returns the sum of a key's numbers among the matching records.
	 *
	 * @throws RecordException
	 */
	public function sum(string $key): int|float
	{
		$sum = $this->aggregate(Aggregate::Sum, $key);

		return is_int($sum) || is_float($sum) ? $sum : 0;
	}

	/**
	 * Returns the mean of a key's numbers among the matching records, or
	 * `null` when there are none.
	 *
	 * @throws RecordException
	 */
	public function avg(string $key): ?float
	{
		$avg = $this->aggregate(Aggregate::Avg, $key);

		return is_int($avg) || is_float($avg) ? (float) $avg : null;
	}

	/**
	 * Checks a key a condition or an order names.
	 *
	 * @throws InvalidRecordQuery When it's empty or has an empty segment.
	 */
	public static function checkKey(string $key): void
	{
		if ($key === '' || in_array('', explode('.', $key), true)) {
			throw new InvalidRecordQuery(sprintf('"%s" isn\'t a key: name a value, with dots between nested keys.', $key));
		}
	}

	/**
	 * Reduces the matching records' values of a key.
	 *
	 * @throws RecordException
	 */
	private function aggregate(Aggregate $function, string $key): int|float|string|bool|null
	{
		self::checkKey($key);

		[$store, $table] = $this->runner();

		return $store->aggregate($table, $this, $function, $key);
	}

	/**
	 * The store and table this query runs on.
	 *
	 * @return array{RecordStore, Table}
	 * @throws InvalidRecordQuery When no store made it.
	 */
	private function runner(): array
	{
		if ($this->store === null || $this->table === null) {
			throw new InvalidRecordQuery('This query wasn\'t made by a store, so it can\'t run itself; pass it to a RecordStore.');
		}

		return [$this->store, $this->table];
	}
}
