<?php

/**
 * Index record matcher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Query\Query;
use Blush\Content\Status;
use Blush\Content\Visibility;
use Blush\Support\Slug;

/**
 * Tests index records against one query's conditions, for
 * `ArraySelector`. What each condition compares against is worked out
 * once, when the matcher is made, not for every record.
 *
 * Filters follow 1.x (D-078): names match slugs; `meta_key` needs a value,
 * and `meta_value` is compared as a slug against the field's value (or
 * any item of a list); dates match the published time in the site
 * timezone. A term condition reads the record's terms for the taxonomy,
 * or, when the taxonomy isn't a content type, a field of that name. A
 * search matches the title or ID in any case (D-230). Each `either()`
 * group needs one of its alternatives to match, and an alternative is
 * tested by its own matcher.
 *
 * @phpstan-import-type RecordArray from IndexRecord
 */
final readonly class RecordMatcher
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
	 * @var list<string>
	 */
	private array $statuses;

	/**
	 * @var list<string>
	 */
	private array $visibilities;

	private bool $landing;

	private ?string $metaValue;

	/**
	 * Term conditions: each taxonomy with its slugs, made slugs.
	 *
	 * @var list<array{string, list<string>}>
	 */
	private array $terms;

	/**
	 * Matchers for each group's alternatives.
	 *
	 * @var list<list<RecordMatcher>>
	 */
	private array $groups;

	public function __construct(private Query $query, private int $now)
	{
		$this->statuses     = array_map(static fn (Status $status): string => $status->value, $query->statuses);
		$this->visibilities = array_map(static fn (Visibility $visibility): string => $visibility->value, $query->visibilities());
		$this->landing      = $query->findsLanding();
		$this->metaValue    = $query->metaValue === null ? null : Slug::from($query->metaValue);
		$this->terms        = array_map(static fn (array $term): array => [$term[0], array_map(Slug::from(...), $term[1])], $query->terms);
		$this->groups       = array_map(
			static fn (array $group): array => array_map(static fn (Query $alternative): self => new self($alternative, $now), $group),
			$query->alternatives
		);
	}

	/**
	 * Whether a record meets every condition.
	 *
	 * @param RecordArray $record
	 */
	public function matches(array $record): bool
	{
		$query  = $this->query;
		$search = $query->search;

		return ! (
			($record['landing'] && ! $this->landing)
			|| ($query->types !== [] && ! in_array($record['type'], $query->types, true))
			|| ($query->directory !== null && $record['directory'] !== $query->directory)
			|| ($query->locale !== null && $record['locale'] !== $query->locale)
			|| ! in_array($record['visibility'], $this->visibilities, true)
			|| ! in_array(IndexRecord::effectiveStatus($record['status'], $record['published'], $this->now)->value, $this->statuses, true)
			|| ($query->names !== [] && ! in_array($record['slug'], $query->names, true))
			|| ($query->excludedNames !== [] && in_array($record['slug'], $query->excludedNames, true))
			|| ($search !== null && mb_stripos($record['title'], $search) === false && mb_stripos($record['id'], $search) === false)
			|| ! $this->matchesDate($record, $query->date)
			|| ($query->updatedSince !== null && $record['updated'] < $query->updatedSince)
			|| ! $this->matchesTerms($record, $this->terms)
			|| ! $this->matchesMeta($record, $query->metaKey, $this->metaValue)
			|| ! array_all($this->groups, static fn (array $group): bool => array_any($group, static fn (self $matcher): bool => $matcher->matches($record)))
		);
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
	 * @param list<array{string, list<string>}> $terms Taxonomies and slugs.
	 */
	private function matchesTerms(array $record, array $terms): bool
	{
		foreach ($terms as [$taxonomy, $slugs]) {
			$held = $record['terms'][$taxonomy] ?? self::slugs($record['values'][$taxonomy] ?? $record['extra'][$taxonomy] ?? null);

			if (array_intersect($slugs, $held) === []) {
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
