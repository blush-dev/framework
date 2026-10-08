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

use Blush\Content\Entry\Position;
use Blush\Storage\Record\Order;
use Blush\Content\Query\Query;
use Blush\Content\Query\Selection;

/**
 * Runs a query over record arrays with plain array filters, the way
 * `PhpIndex` answers queries. Records are expected in ID order, which is
 * file-name order; sorting is stable, so ties keep it.
 *
 * Each record is tested by a `RecordMatcher`. With a fallback language
 * (D-469), a translation group's record in the query's language wins over
 * its fallback's.
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

		if ($query->fallback !== null) {
			$matches = self::translated($matches, $query->language);
		}

		$matches = $this->sort($matches, $query->orderBy, $query->order, $now);
		$paths   = array_column(array_slice($matches, $query->offset, $query->limit), 'path');

		return new Selection($paths, count($matches));
	}

	/**
	 * Returns matching records without the fallback language's where the
	 * query's language matched one of the same translation group (D-469),
	 * so an entry is listed once: in the language, else as its original.
	 *
	 * @param  list<RecordArray> $records
	 * @return list<RecordArray>
	 */
	private static function translated(array $records, ?string $language): array
	{
		$translated = [];

		foreach ($records as $record) {
			if ($record['language'] === $language) {
				$translated[IndexRecord::groupOf($record)] = true;
			}
		}

		return array_values(array_filter(
			$records,
			static fn (array $record): bool => $record['language'] === $language || ! isset($translated[IndexRecord::groupOf($record)])
		));
	}

	/**
	 * Sorts records by a key, then by id for ties (a UUIDv7 is in the
	 * order entries were made), the same way; never by file (D-516).
	 *
	 * @param  list<RecordArray> $records
	 * @return list<RecordArray>
	 */
	private function sort(array $records, string $orderBy, Order $order, int $now): array
	{
		$direction = $order === Order::Desc ? -1 : 1;

		// Entries without a position follow those with one, by title,
		// whichever way the positions run (D-412).
		if ($orderBy === Position::FIELD) {
			usort($records, static fn (array $a, array $b): int => Position::compare(
				is_int($a['values'][Position::FIELD] ?? null) ? $a['values'][Position::FIELD] : null,
				$a['title'],
				is_int($b['values'][Position::FIELD] ?? null) ? $b['values'][Position::FIELD] : null,
				$b['title'],
				$direction
			));

			return $records;
		}

		$keys = array_map(fn (array $record): string|int|float|null => $this->sortValue($record, $orderBy, $now), $records);
		$positions = array_keys($records);

		usort($positions, static fn (int $a, int $b): int => $direction * (self::compare($keys[$a], $keys[$b]) ?: self::compare($records[$a]['id'], $records[$b]['id'])));

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
