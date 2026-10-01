<?php

/**
 * Array query selector.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Query\Order;
use Blush\Content\Query\Query;
use Blush\Content\Query\Selection;

/**
 * Runs a query over record arrays with plain array filters, the way
 * `PhpIndex` answers queries. Records are expected in ID order, which is
 * file-name order; sorting is stable, so ties keep it.
 *
 * Each record is tested by a `RecordMatcher`.
 *
 * @phpstan-import-type RecordArray from IndexRecord
 */
final readonly class ArraySelector
{
	/**
	 * Selects the records a query matches.
	 *
	 * @param array<array-key, RecordArray> $records
	 */
	public function select(array $records, Query $query, int $now): Selection
	{
		$matcher = new RecordMatcher($query, $now);
		$matches = [];

		foreach ($records as $record) {
			if ($matcher->matches($record)) {
				$matches[] = $record;
			}
		}

		$matches = $this->sort($matches, $query->orderBy, $query->order, $now);
		$ids     = array_column(array_slice($matches, $query->offset, $query->limit), 'id');

		return new Selection($ids, count($matches));
	}

	/**
	 * Sorts records by a key, keeping ID order for ties.
	 *
	 * @param  list<RecordArray> $records
	 * @return list<RecordArray>
	 */
	private function sort(array $records, string $orderBy, Order $order, int $now): array
	{
		$direction = $order === Order::Desc ? -1 : 1;

		if ($orderBy === 'filename' || $orderBy === 'path') {
			return $direction === 1 ? $records : array_reverse($records);
		}

		$keys = array_map(fn (array $record): string|int|float|null => $this->sortValue($record, $orderBy, $now), $records);
		$positions = array_keys($records);

		usort($positions, static fn (int $a, int $b): int => $direction * self::compare($keys[$a], $keys[$b]));

		return array_map(static fn (int $position): array => $records[$position], $positions);
	}

	/**
	 * Returns the value a record sorts by.
	 *
	 * @param RecordArray $record
	 */
	private function sortValue(array $record, string $orderBy, int $now): string|int|float|null
	{
		$value = match ($orderBy) {
			'published', 'updated', 'title', 'slug' => $record[$orderBy],
			'name'                                  => $record['slug'],
			'status'                                => IndexRecord::effectiveStatus($record['status'], $record['published'], $now)->value,
			'author'                                => $record['terms']['author'] ?? $record['extra']['author'] ?? null,
			default                                 => $record['values'][$orderBy] ?? $record['extra'][$orderBy] ?? $record['terms'][$orderBy] ?? null
		};

		$value = is_array($value) ? array_first($value) : $value;

		return match (true) {
			is_string($value), is_int($value), is_float($value) => $value,
			is_bool($value)                                     => (int) $value,
			default                                             => null
		};
	}

	/**
	 * Compares two sort values: missing values are lowest, numbers compare
	 * as numbers, and anything else in natural, case-insensitive order.
	 */
	private static function compare(string|int|float|null $a, string|int|float|null $b): int
	{
		return match (true) {
			$a === null && $b === null => 0,
			$a === null                => -1,
			$b === null                => 1,
			! is_string($a) && ! is_string($b) => $a <=> $b,
			default                    => strnatcasecmp((string) $a, (string) $b)
		};
	}
}
