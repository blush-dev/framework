<?php

/**
 * Array evaluator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;
use Blush\Storage\StorageArea;

/**
 * Runs record queries over records in memory, for the stores that keep
 * no index of their own (`FileRecordStore`, `ArrayRecordStore`). It's
 * the reference for how every operator, order, and aggregate behaves
 * (`Operator`); a database driver's SQL answers as it does, which the
 * conformance suite checks.
 *
 * Each call gets the table and a way to read any table's records (for
 * subqueries and refs, which may be in another table of the area), in
 * the order they were added, which ties in an order, and a query
 * without one, keep. A subquery is run once a call.
 *
 * Records are read and tested as rows, plain arrays (`row()`), so a
 * store can hand over what it keeps without building an object for
 * each; only the records found become `Record`s. A store that keeps
 * refs looked up can pass that too (`RefLookup`: the sources referring
 * to each target through a relation, or with `inverse`, the targets
 * each source refers to), which a related condition reads in place of
 * scanning every ref.
 *
 * @phpstan-type Row array{id: string, fields: array<string, mixed>, content?: ?string, version?: ?string}
 * @phpstan-type RefLookup Closure(StorageArea, string, bool): array<string, list<string>>
 */
final readonly class ArrayEvaluator
{
	/**
	 * Returns a record as a row.
	 *
	 * @return Row
	 */
	public static function row(Record $record): array
	{
		return ['id' => $record->id, 'fields' => $record->fields, 'content' => $record->content, 'version' => $record->version];
	}

	/**
	 * Returns a row as a record, with or without its content.
	 *
	 * @param Row $row
	 */
	public static function record(array $row, bool $content = true): Record
	{
		return new Record($row['id'], $row['fields'], $content ? $row['content'] ?? null : null, $row['version'] ?? null);
	}

	/**
	 * Returns a row's value by key, as `Record::value()` does.
	 *
	 * @param array<array-key, mixed> $row A row (`Row`).
	 */
	public static function value(array $row, string $key): mixed
	{
		if ($key === 'id' || $key === 'content') {
			return $row[$key] ?? null;
		}

		$value = is_array($row['fields'] ?? null) ? $row['fields'] : [];

		if (array_key_exists($key, $value)) {
			return $value[$key];
		}

		foreach (explode('.', $key) as $segment) {
			if (! is_array($value) || ! array_key_exists($segment, $value)) {
				return null;
			}

			$value = $value[$segment];
		}

		return $value;
	}

	/**
	 * Returns the records a query matches, within its limit and offset.
	 *
	 * @param Closure(Table): list<Row> $records
	 * @param ?RefLookup                $refs
	 */
	public function select(Table $table, RecordQuery $query, Closure $records, ?Closure $refs = null): RecordResult
	{
		$matched = $this->sorted($this->matching($table, $query, new ArrayEvaluation($records, $refs)), $query);
		$found   = array_slice($matched, $query->offset, $query->limit);

		return new RecordResult(array_map(static fn (array $row): Record => self::record($row, $query->content), $found), count($matched));
	}

	/**
	 * Returns how many records a query matches.
	 *
	 * @param Closure(Table): list<Row> $records
	 * @param ?RefLookup                $refs
	 */
	public function count(Table $table, RecordQuery $query, Closure $records, ?Closure $refs = null): int
	{
		return count($this->matching($table, $query, new ArrayEvaluation($records, $refs)));
	}

	/**
	 * Returns how many matching records have each value of a key, in the
	 * order values sort in.
	 *
	 * @param  Closure(Table): list<Row> $records
	 * @param  ?RefLookup                $refs
	 * @return list<array{value: bool|int|float|string, count: int}>
	 */
	public function countBy(Table $table, RecordQuery $query, string $key, Closure $records, ?Closure $refs = null): array
	{
		RecordQuery::checkKey($key);

		$counts = [];

		foreach ($this->matching($table, $query, new ArrayEvaluation($records, $refs)) as $record) {
			$value = self::value($record, $key);
			$items = is_array($value) && array_is_list($value) ? $value : [$value];
			$seen  = [];

			foreach ($items as $item) {
				if (! (is_bool($item) || is_int($item) || is_float($item) || is_string($item))) {
					continue;
				}

				$id = self::groupKey($item);

				if (isset($seen[$id])) {
					continue;
				}

				$seen[$id]     = true;
				$counts[$id] ??= ['value' => $item, 'count' => 0];
				$counts[$id]['count']++;
			}
		}

		$counts = array_values($counts);

		usort($counts, static fn (array $a, array $b): int => self::compareSorted($a['value'], $b['value']));

		return $counts;
	}

	/**
	 * Reduces the matching records' values of a key.
	 *
	 * @param Closure(Table): list<Row> $records
	 * @param ?RefLookup                $refs
	 */
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key, Closure $records, ?Closure $refs = null): int|float|string|bool|null
	{
		RecordQuery::checkKey($key);

		$values = array_map(static fn (array $record): mixed => self::value($record, $key), $this->matching($table, $query, new ArrayEvaluation($records, $refs)));

		if ($function === Aggregate::Sum || $function === Aggregate::Avg) {
			$numbers = array_values(array_filter($values, static fn (mixed $value): bool => is_int($value) || is_float($value)));

			if ($function === Aggregate::Sum) {
				return array_sum($numbers);
			}

			return $numbers === [] ? null : array_sum($numbers) / count($numbers);
		}

		$values = array_values(array_filter($values, static fn (mixed $value): bool => is_bool($value) || is_int($value) || is_float($value) || is_string($value)));

		if ($values === []) {
			return null;
		}

		usort($values, self::compareSorted(...));

		/** @var bool|int|float|string */
		return $function === Aggregate::Min ? $values[0] : $values[count($values) - 1];
	}

	/**
	 * Returns whether a value meets one condition on values. A subquery's
	 * values are given resolved, as a list.
	 */
	public static function meets(mixed $value, Operator $operator, mixed $against): bool
	{
		return match ($operator) {
			Operator::Equal          => self::equals($value, $against),
			Operator::NotEqual       => ! self::equals($value, $against),
			Operator::Less           => self::ordered($value, $against, static fn (int $result): bool => $result < 0),
			Operator::LessOrEqual    => self::ordered($value, $against, static fn (int $result): bool => $result <= 0),
			Operator::Greater        => self::ordered($value, $against, static fn (int $result): bool => $result > 0),
			Operator::GreaterOrEqual => self::ordered($value, $against, static fn (int $result): bool => $result >= 0),
			Operator::In             => is_array($against) && array_any($against, static fn (mixed $item): bool => self::equals($value, $item)),
			Operator::NotIn          => is_array($against) && ! array_any($against, static fn (mixed $item): bool => self::equals($value, $item)),
			Operator::Between        => is_array($against)
				&& self::ordered($value, $against[0] ?? null, static fn (int $result): bool => $result >= 0)
				&& self::ordered($value, $against[1] ?? null, static fn (int $result): bool => $result <= 0),
			Operator::Like           => is_string($value) && is_string($against) && preg_match(self::pattern($against), $value) === 1,
			Operator::Contains       => is_array($value) && array_is_list($value) && array_any($value, static fn (mixed $item): bool => self::equals($item, $against)),
			Operator::Intersects     => is_array($value) && array_is_list($value) && is_array($against)
				&& array_any($value, static fn (mixed $item): bool => array_any($against, static fn (mixed $wanted): bool => self::equals($item, $wanted))),
			Operator::Null           => $value === null,
			Operator::NotNull        => $value !== null
		};
	}

	/**
	 * The records that meet a query's conditions, in the order added.
	 *
	 * @return list<Row>
	 */
	private function matching(Table $table, RecordQuery $query, ArrayEvaluation $evaluation): array
	{
		$records = $evaluation->records($table);

		if ($query->conditions->isEmpty()) {
			return $records;
		}

		$test = $this->test($table, $query->conditions, $evaluation);

		return array_values(array_filter($records, $test));
	}

	/**
	 * A group of conditions as one test, built once a query: each
	 * subquery and related condition is worked out as it's built.
	 *
	 * @return Closure(array<array-key, mixed>): bool
	 */
	private function test(Table $table, ConditionGroup $group, ArrayEvaluation $evaluation): Closure
	{
		// No conditions: all of none hold, and none of none do.
		if ($group->isEmpty()) {
			$empty = $group->junction === Junction::All;

			return static fn (array $record): bool => $empty;
		}

		/** @var list<Closure(array<array-key, mixed>): bool> $tests */
		$tests = [];

		foreach ($group->conditions as $condition) {
			$tests[] = match (true) {
				$condition instanceof ConditionGroup => $this->test($table, $condition, $evaluation),
				$condition instanceof Related        => self::kept($this->related($table, $condition, $evaluation)),
				default                              => self::condition(
					$condition->key,
					$condition->operator,
					$condition->value instanceof Subquery ? $this->values($table, $condition->value, $evaluation) : $condition->value
				)
			};
		}

		if (count($tests) === 1) {
			return $tests[0];
		}

		return $group->junction === Junction::All
			? static function (array $record) use ($tests): bool {
				foreach ($tests as $test) {
					if (! $test($record)) {
						return false;
					}
				}

				return true;
			}
			: static function (array $record) use ($tests): bool {
				foreach ($tests as $test) {
					if ($test($record)) {
						return true;
					}
				}

				return false;
			};
	}

	/**
	 * A test that a record's id is among those kept.
	 *
	 * @param  array<string, true> $kept
	 * @return Closure(array<array-key, mixed>): bool
	 */
	private static function kept(array $kept): Closure
	{
		return static fn (array $record): bool => is_string($record['id'] ?? null) && isset($kept[$record['id']]);
	}

	/**
	 * One condition on values as a test. `in` and `not in` over plain
	 * values look them up by key.
	 *
	 * @return Closure(array<array-key, mixed>): bool
	 */
	private static function condition(string $key, Operator $operator, mixed $against): Closure
	{
		$fast = self::fastCondition($key, $operator, $against);

		if ($fast !== null) {
			return $fast;
		}

		if (($operator === Operator::In || $operator === Operator::NotIn) && is_array($against) && array_all($against, static fn (mixed $item): bool => is_string($item))) {
			$set = [];
			$in  = $operator === Operator::In;

			foreach ($against as $item) {
				if (is_string($item)) {
					$set[$item] = true;
				}
			}

			return static function (array $record) use ($key, $set, $in): bool {
				$value = self::value($record, $key);

				return is_string($value) ? isset($set[$value]) === $in : ! $in;
			};
		}

		return static fn (array $record): bool => self::meets(self::value($record, $key), $operator, $against);
	}

	/**
	 * A condition with a common operator, read through a reader made for
	 * its key and compared inline, which answers as `meets()` does;
	 * `null` for the rest.
	 *
	 * @return ?Closure(array<array-key, mixed>): bool
	 */
	private static function fastCondition(string $key, Operator $operator, mixed $against): ?Closure
	{
		$read = self::reader($key);

		if (($operator === Operator::In || $operator === Operator::NotIn) && is_array($against) && array_all($against, static fn (mixed $item): bool => is_string($item))) {
			$set = [];

			foreach ($against as $item) {
				if (is_string($item)) {
					$set[$item] = true;
				}
			}

			$in = $operator === Operator::In;

			return static fn (array $record): bool => is_string($value = $read($record)) ? isset($set[$value]) === $in : ! $in;
		}

		if ($operator === Operator::Intersects && is_array($against) && array_all($against, static fn (mixed $item): bool => is_string($item))) {
			$set = array_fill_keys(array_filter($against, is_string(...)), true);

			return static function (array $record) use ($read, $set): bool {
				$value = $read($record);

				if (! is_array($value) || ! array_is_list($value)) {
					return false;
				}

				foreach ($value as $item) {
					if (is_string($item) && isset($set[$item])) {
						return true;
					}
				}

				return false;
			};
		}

		if ($operator === Operator::Between && is_array($against) && is_string($against[0] ?? null) && is_string($against[1] ?? null)) {
			[$low, $high] = [$against[0], $against[1]];

			return static fn (array $record): bool => is_string($value = $read($record)) && strcmp($value, $low) >= 0 && strcmp($value, $high) <= 0;
		}

		if ($operator === Operator::Like && is_string($against)) {
			$text = self::contains($against);

			return $text === null ? null : static fn (array $record): bool => is_string($value = $read($record)) && ($text === '' || mb_stripos($value, $text) !== false);
		}

		return match (true) {
			$operator === Operator::Null                                                                         => static fn (array $record): bool => $read($record) === null,
			$operator === Operator::NotNull                                                                      => static fn (array $record): bool => $read($record) !== null,
			$operator === Operator::Equal && (is_string($against) || is_bool($against) || $against === null)    => static fn (array $record): bool => $read($record) === $against,
			$operator === Operator::NotEqual && (is_string($against) || is_bool($against) || $against === null) => static fn (array $record): bool => $read($record) !== $against,
			is_string($against) && $operator === Operator::Less                                                  => static fn (array $record): bool => is_string($value = $read($record)) && strcmp($value, $against) < 0,
			is_string($against) && $operator === Operator::LessOrEqual                                           => static fn (array $record): bool => is_string($value = $read($record)) && strcmp($value, $against) <= 0,
			is_string($against) && $operator === Operator::Greater                                               => static fn (array $record): bool => is_string($value = $read($record)) && strcmp($value, $against) > 0,
			is_string($against) && $operator === Operator::GreaterOrEqual                                        => static fn (array $record): bool => is_string($value = $read($record)) && strcmp($value, $against) >= 0,
			default                                                                                              => null
		};
	}

	/**
	 * A reader for a key's value in a row, as `value()` reads it, made
	 * once a condition: `id`, `content`, a top-level field, a nested
	 * field two keys down, or, deeper, `value()` itself.
	 *
	 * @return Closure(array<array-key, mixed>): mixed
	 */
	private static function reader(string $key): Closure
	{
		if ($key === 'id' || $key === 'content') {
			return static fn (array $record): mixed => $record[$key] ?? null;
		}

		$segments = explode('.', $key);

		return match (count($segments)) {
			1       => static fn (array $record): mixed => is_array($fields = $record['fields'] ?? null) ? $fields[$key] ?? null : null,
			2       => static fn (array $record): mixed => ! is_array($fields = $record['fields'] ?? null) ? null : ($fields[$key] ?? (is_array($outer = $fields[$segments[0]] ?? null) ? $outer[$segments[1]] ?? null : null)),
			default => static fn (array $record): mixed => self::value($record, $key)
		};
	}

	/**
	 * The text a `like` pattern finds anywhere (`%text%`, with nothing
	 * else that's a wildcard), unescaped, or `null` for any other
	 * pattern.
	 */
	private static function contains(string $pattern): ?string
	{
		if (! str_starts_with($pattern, '%') || ! str_ends_with($pattern, '%') || strlen($pattern) < 2) {
			return null;
		}

		$inner = substr($pattern, 1, -1);
		$text  = '';

		for ($i = 0, $length = strlen($inner); $i < $length; $i++) {
			$character = $inner[$i];

			if ($character === '\\' && $i + 1 < $length) {
				$text .= $inner[++$i];
			} elseif ($character === '%' || $character === '_' || $character === '\\') {
				return null;
			} else {
				$text .= $character;
			}
		}

		return $text;
	}

	/**
	 * A subquery's values, run once a call.
	 *
	 * @return list<bool|int|float|string>
	 */
	private function values(Table $outer, Subquery $subquery, ArrayEvaluation $evaluation): array
	{
		return $evaluation->remember($subquery, function () use ($outer, $subquery, $evaluation): array {
			$table  = $subquery->table ?? $outer;
			$found  = array_slice($this->sorted($this->matching($table, $subquery->query, $evaluation), $subquery->query), $subquery->query->offset, $subquery->query->limit);
			$values = [];

			foreach ($found as $record) {
				$value = self::value($record, $subquery->key);

				if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
					$values[] = $value;
				}
			}

			return $values;
		});
	}

	/**
	 * The ids of the records a related condition keeps, as keys, run
	 * once a call.
	 *
	 * @return array<string, true>
	 */
	private function related(Table $table, Related $related, ArrayEvaluation $evaluation): array
	{
		return $evaluation->remember($related, function () use ($table, $related, $evaluation): array {
			$targets     = $related->targets instanceof Subquery ? $this->values($table, $related->targets, $evaluation) : $related->targets;
			$wanted      = array_flip(array_map(static fn (bool|int|float|string $id): string => strtolower((string) $id), $targets));
			[$from, $to] = $related->inverse ? ['target_id', 'source_id'] : ['source_id', 'target_id'];
			$kept        = [];
			$lookup      = $evaluation->refs($table->area, $related->relation, $related->inverse);

			if ($lookup !== null) {
				foreach (array_keys($wanted) as $target) {
					foreach ($lookup[(string) $target] ?? [] as $source) {
						$kept[$source] = true;
					}
				}

				return $kept;
			}

			foreach ($evaluation->records(Ref::table($table->area)) as $ref) {
				$source = $ref['fields'][$from] ?? null;
				$target = $ref['fields'][$to] ?? null;

				if (($ref['fields']['relation'] ?? null) === $related->relation && is_string($source) && is_string($target) && isset($wanted[strtolower($target)])) {
					$kept[strtolower($source)] = true;
				}
			}

			return $kept;
		});
	}

	/**
	 * Records in a query's order. The sort is stable, so ties keep the
	 * order records were added in.
	 *
	 * @param  list<Row> $records
	 * @return list<Row>
	 */
	private function sorted(array $records, RecordQuery $query): array
	{
		if ($query->sorts === []) {
			return $records;
		}

		$native = self::sortedNatively($records, $query);

		if ($native !== null) {
			return $native;
		}

		// Each record's sort values, read once.
		$keys       = [];
		$directions = array_map(static fn (Sort $sort): int => $sort->order === Order::Desc ? -1 : 1, $query->sorts);

		foreach ($records as $position => $record) {
			foreach ($query->sorts as $index => $sort) {
				$keys[$position][$index] = self::sortKey(self::value($record, $sort->key));
			}
		}

		$positions = array_keys($records);

		usort($positions, static function (int $a, int $b) use ($keys, $directions): int {
			foreach ($directions as $index => $direction) {
				$x = $keys[$a][$index];
				$y = $keys[$b][$index];

				// Null, or no value, sorts last whichever way.
				if ($x === null || $y === null) {
					$result = ($x === null ? 1 : 0) - ($y === null ? 1 : 0);
				} elseif ($x[0] !== $y[0]) {
					$result = ($x[0] <=> $y[0]) * $direction;
				} else {
					$result = (is_string($x[1]) && is_string($y[1]) ? strcmp($x[1], $y[1]) <=> 0 : $x[1] <=> $y[1]) * $direction;
				}

				if ($result !== 0) {
					return $result;
				}
			}

			// Ties keep the order added.
			return $a <=> $b;
		});

		return array_map(static fn (int $position): array => $records[$position], $positions);
	}

	/**
	 * Records sorted by PHP's own sort, when every sort key's values
	 * (other than null) are all text or all numbers: nulls last, text
	 * without regard to case, ties in the order added, as the comparator
	 * sorts them. `null` when a key's values are mixed.
	 *
	 * @param  list<Row> $records
	 * @return ?list<Row>
	 */
	private static function sortedNatively(array $records, RecordQuery $query): ?array
	{
		$columns = [];

		foreach ($query->sorts as $sort) {
			$nulls  = [];
			$values = [];
			$kind   = null;

			foreach ($records as $record) {
				$value = self::value($record, $sort->key);

				if ($value === null) {
					$nulls[]  = 1;
					$values[] = 0;

					continue;
				}

				$is = match (true) {
					is_string($value)                  => SORT_STRING,
					is_int($value) || is_float($value) => SORT_NUMERIC,
					default                            => null
				};

				if ($is === null || ($kind !== null && $kind !== $is)) {
					return null;
				}

				$kind     = $is;
				$nulls[]  = 0;
				$values[] = is_string($value) ? mb_strtolower($value) : $value;
			}

			// Nulls take the kind's empty value, which their column puts last.
			if ($kind === SORT_STRING) {
				$values = array_map(static fn (mixed $value): mixed => $value === 0 ? '' : $value, $values);
			}

			array_push($columns, $nulls, SORT_ASC, SORT_NUMERIC, $values, $sort->order === Order::Desc ? SORT_DESC : SORT_ASC, $kind ?? SORT_NUMERIC);
		}

		$positions = array_keys($records);

		array_push($columns, $positions, SORT_ASC, SORT_NUMERIC);

		$first = array_shift($columns);

		if (! is_array($first)) {
			return null;
		}

		array_multisort($first, ...$columns);

		$sorted = $columns[count($columns) - 3];

		return is_array($sorted) ? array_map(static fn (mixed $position): array => $records[(int) $position], $sorted) : null;
	}

	/**
	 * Whether two values are equal: numbers as numbers, everything else
	 * strictly.
	 */
	private static function equals(mixed $a, mixed $b): bool
	{
		if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
			return $a == $b;
		}

		return $a === $b;
	}

	/**
	 * Whether two values compare (`compare()`) as a test wants; never
	 * when they don't compare at all.
	 *
	 * @param Closure(int): bool $test
	 */
	private static function ordered(mixed $a, mixed $b, Closure $test): bool
	{
		$result = self::compare($a, $b);

		return $result !== null && $test($result);
	}

	/**
	 * Compares two numbers or two strings (`-1`, `0`, `1`), or `null`
	 * when they aren't both one or the other. Text compares byte by byte.
	 */
	private static function compare(mixed $a, mixed $b): ?int
	{
		if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
			return $a <=> $b;
		}

		if (is_string($a) && is_string($b)) {
			return strcmp($a, $b) <=> 0;
		}

		return null;
	}

	/**
	 * A value as it sorts, worked out once: `null`, or its rank (false and
	 * true, numbers, text, lists and maps) and what to compare within it
	 * (text lowercased, D-649; lists and maps as their JSON), as
	 * `compareSorted()` orders them.
	 *
	 * @return ?array{int, bool|int|float|string}
	 */
	private static function sortKey(mixed $value): ?array
	{
		return match (true) {
			$value === null                    => null,
			is_bool($value)                    => [0, $value],
			is_int($value) || is_float($value) => [1, $value],
			is_string($value)                  => [2, mb_strtolower($value)],
			default                            => [3, (string) json_encode($value)]
		};
	}

	/**
	 * Compares any two values that aren't null, for ordering: false
	 * before true, then numbers, then text (without regard to case,
	 * D-649), then lists and maps (by their JSON).
	 */
	private static function compareSorted(mixed $a, mixed $b): int
	{
		$rank = static fn (mixed $value): int => match (true) {
			is_bool($value)                     => 0,
			is_int($value) || is_float($value)  => 1,
			is_string($value)                   => 2,
			default                             => 3
		};

		$ranks = $rank($a) <=> $rank($b);

		if ($ranks !== 0) {
			return $ranks;
		}

		return match (true) {
			is_string($a) && is_string($b) => strcmp(mb_strtolower($a), mb_strtolower($b)) <=> 0,
			$rank($a) < 2                  => $a <=> $b,
			default                        => strcmp((string) json_encode($a), (string) json_encode($b)) <=> 0
		};
	}

	/**
	 * A value's key among the counted, telling `1`, `1.0`, `"1"`, and
	 * `true` apart only as equality does.
	 */
	private static function groupKey(bool|int|float|string $value): string
	{
		return match (true) {
			is_bool($value)                    => 'b:' . ($value ? '1' : '0'),
			is_int($value) || is_float($value) => 'n:' . (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX ? (string) (int) $value : (string) $value),
			default                            => 's:' . $value
		};
	}

	/**
	 * A `like` pattern as a regular expression: `%` any run, `_` one
	 * character, `\` escaping either, without regard to case.
	 */
	private static function pattern(string $like): string
	{
		$regex = '';

		for ($i = 0, $length = mb_strlen($like); $i < $length; $i++) {
			$character = mb_substr($like, $i, 1);

			if ($character === '\\' && $i + 1 < $length) {
				$regex .= preg_quote(mb_substr($like, ++$i, 1), '/');
			} elseif ($character === '%') {
				$regex .= '.*';
			} elseif ($character === '_') {
				$regex .= '.';
			} else {
				$regex .= preg_quote($character, '/');
			}
		}

		return "/\\A{$regex}\\z/isu";
	}
}
