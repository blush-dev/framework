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
use Blush\Content\Status;
use Blush\Content\Visibility;
use Blush\Support\Slug;

/**
 * Runs a query over record arrays with plain array filters, the way
 * `PhpIndex` answers queries. Records are expected in ID order, which is
 * file-name order; sorting is stable, so ties keep it.
 *
 * Filters follow 1.x (D-078): names match slugs; `meta_key` needs a value,
 * and `meta_value` is compared as a slug against the field's value (or
 * any item of a list); dates match the published time in the site
 * timezone. A term condition reads the record's terms for the taxonomy,
 * or, when the taxonomy isn't a content type, a field of that name.
 *
 * @phpstan-import-type RecordArray from IndexRecord
 */
final readonly class ArraySelector
{
	/**
	 * Where each date part sits in a record's `YmdHis` date.
	 *
	 * @var array<string, array{int, int}>
	 */
	private const array DATE_OFFSETS = [
		'year'   => [0, 4],
		'month'  => [4, 2],
		'day'    => [6, 2],
		'hour'   => [8, 2],
		'minute' => [10, 2],
		'second' => [12, 2]
	];

	/**
	 * Selects the records a query matches.
	 *
	 * @param array<array-key, RecordArray> $records
	 */
	public function select(array $records, Query $query, int $now): Selection
	{
		$statuses     = array_map(static fn (Status $status): string => $status->value, $query->statuses);
		$visibilities = array_map(static fn (Visibility $visibility): string => $visibility->value, $query->visibilities());
		$landing      = $query->findsLanding();
		$metaValue    = $query->metaValue === null ? null : Slug::from($query->metaValue);
		$matches      = [];

		foreach ($records as $record) {
			if (
				($record['landing'] && ! $landing)
				|| ($query->types !== [] && ! in_array($record['type'], $query->types, true))
				|| ($query->directory !== null && $record['directory'] !== $query->directory)
				|| ($query->locale !== null && $record['locale'] !== $query->locale)
				|| ! in_array($record['visibility'], $visibilities, true)
				|| ! in_array(IndexRecord::effectiveStatus($record['status'], $record['published'], $now)->value, $statuses, true)
				|| ($query->names !== [] && ! in_array($record['slug'], $query->names, true))
				|| ($query->excludedNames !== [] && in_array($record['slug'], $query->excludedNames, true))
				|| ! $this->matchesDate($record, $query->date)
				|| ! $this->matchesTerms($record, $query->terms)
				|| ! $this->matchesMeta($record, $query->metaKey, $metaValue)
			) {
				continue;
			}

			$matches[] = $record;
		}

		$matches = $this->sort($matches, $query->orderBy, $query->order);
		$ids     = array_column(array_slice($matches, $query->offset, $query->limit), 'id');

		return new Selection($ids, count($matches));
	}

	/**
	 * @param RecordArray        $record
	 * @param array<string, int> $date
	 */
	private function matchesDate(array $record, array $date): bool
	{
		if ($date === []) {
			return true;
		}

		if ($record['date'] === null) {
			return false;
		}

		foreach ($date as $part => $value) {
			[$start, $length] = self::DATE_OFFSETS[$part] ?? [0, 0];

			if ($length === 0 || (int) substr($record['date'], $start, $length) !== $value) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param RecordArray                       $record
	 * @param list<array{string, list<string>}> $terms
	 */
	private function matchesTerms(array $record, array $terms): bool
	{
		foreach ($terms as [$taxonomy, $slugs]) {
			$held = $record['terms'][$taxonomy] ?? self::slugs($record['values'][$taxonomy] ?? $record['extra'][$taxonomy] ?? null);

			if (array_intersect(array_map(Slug::from(...), $slugs), $held) === []) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param RecordArray $record
	 */
	private function matchesMeta(array $record, ?string $key, ?string $value): bool
	{
		if ($key === null) {
			return true;
		}

		$held = $record['values'][$key] ?? $record['extra'][$key] ?? null;

		if ($held === null || $held === '' || $held === []) {
			return false;
		}

		return $value === null || in_array($value, self::slugs($held), true);
	}

	/**
	 * Sorts records by a key, keeping ID order for ties.
	 *
	 * @param  list<RecordArray> $records
	 * @return list<RecordArray>
	 */
	private function sort(array $records, string $orderBy, Order $order): array
	{
		$direction = $order === Order::Desc ? -1 : 1;

		if ($orderBy === 'filename' || $orderBy === 'path') {
			return $direction === 1 ? $records : array_reverse($records);
		}

		$keys = array_map(fn (array $record): string|int|float|null => $this->sortValue($record, $orderBy), $records);
		$positions = array_keys($records);

		usort($positions, static fn (int $a, int $b): int => $direction * self::compare($keys[$a], $keys[$b]));

		return array_map(static fn (int $position): array => $records[$position], $positions);
	}

	/**
	 * Returns the value a record sorts by.
	 *
	 * @param RecordArray $record
	 */
	private function sortValue(array $record, string $orderBy): string|int|float|null
	{
		$value = match ($orderBy) {
			'published', 'updated', 'title', 'slug' => $record[$orderBy],
			'name'                                  => $record['slug'],
			'author'                                => $record['terms']['author'] ?? $record['extra']['author'] ?? null,
			default                                 => $record['values'][$orderBy] ?? $record['extra'][$orderBy] ?? null
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

	/**
	 * Returns the slugs of a value or of a list's items.
	 *
	 * @return list<string>
	 */
	private static function slugs(mixed $value): array
	{
		$slugs = [];

		foreach (is_array($value) ? $value : [$value] as $item) {
			if (is_string($item) || is_int($item) || is_float($item)) {
				$slugs[] = Slug::from((string) $item);
			}
		}

		return $slugs;
	}
}
