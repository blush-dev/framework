<?php

/**
 * Query compiler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Closure;
use DateTimeImmutable;
use Blush\Content\Entry\Position;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Query;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Core\Language;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\Subquery;
use Blush\Support\Slug;

/**
 * Turns content's `Query` into a `RecordQuery` over the `entries` table
 * (D-649), so any driver answers it, and answers it alike:
 *
 * - **Status:** "published" is a published entry whose `published` time
 *   has come (or that has none), "scheduled" one whose time is still to
 *   come, at the time given.
 * - **Terms** (`whereTerm()`, 1.x `author`): entries referring to a term
 *   of the relation's types with the slug (refs), or writing the slug
 *   under the relation's front matter keys.
 * - **`meta_key`/`meta_value`:** the value compared as a slug.
 * - **Dates** (`date()`): a range of `published` times in the site's
 *   timezone, from the year down (`year`, then `month`, and so on);
 *   other combinations aren't matched.
 * - **Languages:** a query in a language with a fallback (D-469) finds
 *   both, leaving out a fallback entry when the entry, or its original,
 *   has a translation the query finds in its language.
 * - **Folders and parent keys** ask the store (`EntryLocations`).
 * - **Order:** the record layer's rules (D-648): the key, then the
 *   order entries were made in; positions, then titles.
 */
final readonly class QueryCompiler
{
	public function __construct(
		private ContentTypes $types,
		private AppConfig $app
	) {}

	/**
	 * Compiles a query, as of a Unix time.
	 *
	 * @throws InvalidQuery When a date can't be matched.
	 */
	public function compile(Query $query, int $now, EntryLocations $locations): RecordQuery
	{
		$records = $this->conditions(new RecordQuery(), $query, $now, $locations)->offset($query->offset)->limit($query->limit);

		foreach ($this->sorts($query) as [$key, $order]) {
			$records = $records->orderBy($key, $order);
		}

		return $records;
	}

	/**
	 * Adds a query's conditions.
	 *
	 * @throws InvalidQuery
	 */
	private function conditions(RecordQuery $records, Query $query, int $now, EntryLocations $locations, bool $language = true): RecordQuery
	{
		if (! $query->findsLanding()) {
			$records = $records->where('slug', '!=', '');
		}

		if ($query->types !== []) {
			$records = $records->where('type', 'in', $query->types);
		}

		if ($query->directory !== null) {
			$records = $records->where('id', 'in', $locations->idsIn([$query->directory]));
		}

		if ($query->excludedDirectories !== []) {
			$records = $records->where('id', 'not in', $locations->idsIn($query->excludedDirectories));
		}

		if ($query->locale !== null) {
			$records = $this->locale($records, $query->locale);
		}

		$records = $records->where('visibility', 'in', array_map(static fn (Visibility $visibility): string => $visibility->value, $query->visibilities()));
		$records = $this->statuses($records, $query->statuses, $now);

		if ($query->parent !== false) {
			$records = $query->parent === null
				? $records->where('parent_id', 'null')
				: $records->where('parent_id', 'in', $locations->idsWithKey($query->parent));
		}

		if ($query->names !== []) {
			$records = $records->where('slug', 'in', self::slugs($query->names));
		}

		if ($query->excludedNames !== []) {
			$records = $records->where('slug', 'not in', self::slugs($query->excludedNames));
		}

		if ($query->search !== null) {
			$like    = '%' . addcslashes($query->search, '%_\\') . '%';
			$records = $records->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('title', 'like', $like),
				static fn (RecordQuery $q): RecordQuery => $q->where('slug', 'like', $like)
			);
		}

		if ($query->date !== []) {
			$records = $records->where('published', 'between', $this->dateRange($query->date));
		}

		if ($query->updatedSince !== null) {
			$records = $records->where('updated', '>=', EntryTable::time($query->updatedSince));
		}

		foreach ($query->terms as [$key, $slugs]) {
			$records = $this->term($records, $key, $slugs);
		}

		if ($query->metaKey !== null) {
			$records = $query->metaValue === null
				? $records->where("slugs.{$query->metaKey}", 'not null')
				: $records->where("slugs.{$query->metaKey}", 'contains', Slug::from($query->metaValue));
		}

		foreach ($query->alternatives as $group) {
			$records = $records->whereAny(...array_map(
				fn (Query $alternative): Closure => fn (RecordQuery $q): RecordQuery => $this->conditions($q, $alternative, $now, $locations),
				$group
			));
		}

		return $language ? $this->language($records, $query, $now, $locations) : $records;
	}

	/**
	 * Adds the query's language and its fallback (D-469): a fallback
	 * entry stands in only when neither it nor its original has a
	 * translation the query finds in the language.
	 *
	 * @throws InvalidQuery
	 */
	private function language(RecordQuery $records, Query $query, int $now, EntryLocations $locations): RecordQuery
	{
		if ($query->language === null || $query->language === Query::ANY_LANGUAGE) {
			return $records;
		}

		if ($query->fallback === null) {
			return $records->where('language', '=', $query->language);
		}

		$found  = $this->conditions(new RecordQuery(), $query, $now, $locations, language: false)->where('language', '=', $query->language);
		$byId   = new Subquery($found, 'id');
		$byOriginal = new Subquery($found, 'original_id');

		return $records
			->where('language', 'in', [$query->language, $query->fallback])
			->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('language', '=', $query->language),
				static fn (RecordQuery $q): RecordQuery => $q
					->where('id', 'not in', $byOriginal)
					->whereAny(
						static fn (RecordQuery $q): RecordQuery => $q->where('original_id', 'null'),
						static fn (RecordQuery $q): RecordQuery => $q->where('original_id', 'not in', $byId)->where('original_id', 'not in', $byOriginal)
					)
			);
	}

	/**
	 * Adds statuses, "published" and "scheduled" told apart by time.
	 *
	 * @param list<Status> $statuses
	 */
	private function statuses(RecordQuery $records, array $statuses, int $now): RecordQuery
	{
		if ($statuses === []) {
			return $records->whereAny();
		}

		if (count(array_unique(array_map(static fn (Status $status): string => $status->value, $statuses))) === count(Status::cases())) {
			return $records;
		}

		$time = EntryTable::time($now);

		return $records->whereAny(...array_map(static fn (Status $status): Closure => match ($status) {
			Status::Published => static fn (RecordQuery $q): RecordQuery => $q->where('status', '=', Status::Published->value)->whereAny(
				static fn (RecordQuery $q): RecordQuery => $q->where('published', 'null'),
				static fn (RecordQuery $q): RecordQuery => $q->where('published', '<=', $time)
			),
			Status::Scheduled => static fn (RecordQuery $q): RecordQuery => $q->where('status', '=', Status::Published->value)->where('published', '>', $time),
			default           => static fn (RecordQuery $q): RecordQuery => $q->where('status', '=', $status->value)
		}, $statuses));
	}

	/**
	 * Adds a term condition: refs to a term of the relation's types with
	 * one of the slugs, or one of the slugs written under the relation's
	 * front matter keys, which credits a slug with no entry yet (an
	 * account's author before its profile is written) and is how files
	 * without ids name terms (D-078). A key no relation names reads front
	 * matter under that key.
	 *
	 * @param list<string> $slugs
	 */
	private function term(RecordQuery $records, string $key, array $slugs): RecordQuery
	{
		$slugs        = array_values(array_unique(array_map(Slug::from(...), $slugs)));
		$last         = array_values(array_unique(array_map(static fn (string $slug): string => basename($slug), $slugs)));
		$relations    = $this->termRelations($key);
		$written      = $relations === [] ? [$key] : array_values(array_unique(array_merge(...array_map(static fn (Relation $relation): array => $relation->keys(), $relations))));
		$alternatives = array_map(
			static fn (string $field): Closure => static fn (RecordQuery $q): RecordQuery => $q->where("slugs.{$field}", 'intersects', $slugs),
			$written
		);

		foreach ($relations as $relation) {
			$terms          = new Subquery(new RecordQuery()->where('type', 'in', $relation->to)->where('slug', 'in', $last), 'id');
			$name           = $relation->name;
			$alternatives[] = static fn (RecordQuery $q): RecordQuery => $q->whereRelated($name, $terms);
		}

		return $records->whereAny(...$alternatives);
	}

	/**
	 * The relations a term key names: those whose term key it is
	 * (`category`, `profile.authors`), and every credit relation, for the
	 * profiles type's name (D-602).
	 *
	 * @return list<Relation>
	 */
	private function termRelations(string $key): array
	{
		return array_values(array_filter(
			$this->types->relations(),
			static fn (Relation $relation): bool => $relation->termKey() === $key || ($relation->kind === RelationKind::Credit && in_array($key, $relation->to, true))
		));
	}

	/**
	 * Adds a locale: an entry in another language has its language's;
	 * one in the default language, its own (`locale` in front matter),
	 * else the site's.
	 */
	private function locale(RecordQuery $records, string $locale): RecordQuery
	{
		$languages = $this->app->languages;
		$others    = array_values(array_map(
			static fn (Language $language): string => $language->code,
			array_filter($languages->others(), static fn (Language $language): bool => $language->locale === $locale)
		));
		$default   = $languages->default->code;

		return $records->whereAny(
			static fn (RecordQuery $q): RecordQuery => $q->where('language', 'in', $others),
			static fn (RecordQuery $q): RecordQuery => $q->where('language', '=', $default)->where('fields.locale', '=', $locale),
			fn (RecordQuery $q): RecordQuery => $this->app->locale === $locale
				? $q->where('language', '=', $default)->where('fields.locale', 'null')
				: $q->whereAny()
		);
	}

	/**
	 * The range of `published` times a date's parts cover, in the site's
	 * timezone, as the table keeps times.
	 *
	 * @param  array<string, int> $date
	 * @return array{string, string}
	 * @throws InvalidQuery When the parts don't run from the year down.
	 */
	private function dateRange(array $date): array
	{
		$parts = array_slice(Query::DATE_PARTS, 0, count($date));

		if (array_diff($parts, array_keys($date)) !== []) {
			throw new InvalidQuery(sprintf('Dates match from the year down (%s); %s given.', implode(', ', Query::DATE_PARTS), implode(', ', array_keys($date))));
		}

		$start = new DateTimeImmutable('now', $this->app->timezone())
			->setDate($date['year'], $date['month'] ?? 1, $date['day'] ?? 1)
			->setTime($date['hour'] ?? 0, $date['minute'] ?? 0, $date['second'] ?? 0);
		$unit  = Query::DATE_PARTS[count($date) - 1];
		$end   = $start->modify("+1 {$unit}")->modify('-1 second');

		return [EntryTable::time($start->getTimestamp()), EntryTable::time($end->getTimestamp())];
	}

	/**
	 * The sorts a query's order is: its key (a column, or a front matter
	 * field); positions then titles for `position`; and for a term key
	 * (`category`, the profiles type's name), the slugs written under its
	 * relations' front matter keys.
	 *
	 * @return list<array{string, Order}>
	 */
	private function sorts(Query $query): array
	{
		$relations = $this->termRelations($query->orderBy);
		$written   = array_values(array_unique(array_merge(...array_map(static fn (Relation $relation): array => $relation->keys(), $relations))));

		return match (true) {
			in_array($query->orderBy, ['published', 'updated', 'title', 'slug', 'status'], true) => [[$query->orderBy, $query->order]],
			$query->orderBy === 'name'                                                           => [['slug', $query->order]],
			$query->orderBy === Position::FIELD                                                  => [['position', $query->order], ['title', Order::Asc]],
			$written !== []                                                                      => array_map(static fn (string $field): array => ["slugs.{$field}", $query->order], $written),
			default                                                                              => [["fields.{$query->orderBy}", $query->order]]
		};
	}

	/**
	 * Names as the table keeps slugs: a landing page's `index` is `''`.
	 *
	 * @param  list<string> $names
	 * @return list<string>
	 */
	private static function slugs(array $names): array
	{
		return array_map(static fn (string $name): string => $name === 'index' ? '' : $name, $names);
	}
}
