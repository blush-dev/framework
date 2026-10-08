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

/**
 * Runs record queries over records in memory, for the stores that keep
 * no index of their own (`FileRecordStore`, `ArrayRecordStore`). It's
 * the reference for how every operator, order, and aggregate behaves
 * (`Operator`); a database driver's SQL answers as it does, which the
 * conformance suite checks.
 *
 * Records are given in the order they were added, which ties in an
 * order, and a query without one, keep.
 */
final readonly class ArrayEvaluator
{
	/**
	 * Returns the records a query matches, within its limit and offset.
	 *
	 * @param list<Record> $records
	 */
	public function select(array $records, RecordQuery $query): RecordResult
	{
		$matched = $this->sorted($this->matching($records, $query), $query);

		return new RecordResult(array_slice($matched, $query->offset, $query->limit), count($matched));
	}

	/**
	 * Returns how many records a query matches.
	 *
	 * @param list<Record> $records
	 */
	public function count(array $records, RecordQuery $query): int
	{
		return count($this->matching($records, $query));
	}

	/**
	 * Returns how many matching records have each value of a key, in the
	 * order values sort in.
	 *
	 * @param  list<Record> $records
	 * @return list<array{value: bool|int|float|string, count: int}>
	 */
	public function countBy(array $records, RecordQuery $query, string $key): array
	{
		RecordQuery::checkKey($key);

		$counts = [];

		foreach ($this->matching($records, $query) as $record) {
			$value = $record->value($key);
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
	 * @param list<Record> $records
	 */
	public function aggregate(array $records, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		RecordQuery::checkKey($key);

		$values = array_map(static fn (Record $record): mixed => $record->value($key), $this->matching($records, $query));

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
	 * Returns whether a record meets a group of conditions.
	 */
	public function matches(Record $record, ConditionGroup $group): bool
	{
		$test = fn (Condition|ConditionGroup $condition): bool => $condition instanceof ConditionGroup
			? $this->matches($record, $condition)
			: self::meets($record->value($condition->key), $condition->operator, $condition->value);

		// No conditions: all of none hold, and none of none do.
		if ($group->isEmpty()) {
			return $group->junction === Junction::All;
		}

		return $group->junction === Junction::All
			? array_all($group->conditions, static fn (Condition|ConditionGroup $condition): bool => $test($condition))
			: array_any($group->conditions, static fn (Condition|ConditionGroup $condition): bool => $test($condition));
	}

	/**
	 * Returns whether a value meets one condition.
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
			Operator::Null           => $value === null,
			Operator::NotNull        => $value !== null
		};
	}

	/**
	 * The records that meet a query's conditions, in the order given.
	 *
	 * @param  list<Record> $records
	 * @return list<Record>
	 */
	private function matching(array $records, RecordQuery $query): array
	{
		return array_values(array_filter($records, fn (Record $record): bool => $this->matches($record, $query->conditions)));
	}

	/**
	 * Records in a query's order. The sort is stable, so ties keep the
	 * order records were added in.
	 *
	 * @param  list<Record> $records
	 * @return list<Record>
	 */
	private function sorted(array $records, RecordQuery $query): array
	{
		if ($query->sorts === []) {
			return $records;
		}

		usort($records, static function (Record $a, Record $b) use ($query): int {
			foreach ($query->sorts as $sort) {
				$x = $a->value($sort->key);
				$y = $b->value($sort->key);

				// Null, or no value, sorts last whichever way.
				if ($x === null || $y === null) {
					$result = ($x === null ? 1 : 0) - ($y === null ? 1 : 0);
				} else {
					$result = self::compareSorted($x, $y) * ($sort->order === Order::Desc ? -1 : 1);
				}

				if ($result !== 0) {
					return $result;
				}
			}

			return 0;
		});

		return $records;
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
	 * when they aren't both one or the other.
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
	 * Compares any two values that aren't null, for ordering: false
	 * before true, then numbers, then text (byte by byte), then lists
	 * and maps (by their JSON).
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
			is_string($a) && is_string($b) => strcmp($a, $b) <=> 0,
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
