<?php

/**
 * Content query.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

use Closure;
use NoDiscard;
use Blush\Content\Entry\Entry;
use Blush\Content\Status;
use Blush\Content\Visibility;

/**
 * An immutable description of which entries to find, built fluently:
 *
 *     $content->query()
 *         ->type('post')
 *         ->whereTerm('category', 'art')
 *         ->orderBy('published', Order::Desc)
 *         ->paginate(perPage: 10, page: $page);
 *
 * or from 1.x query arguments (D-078) with `fromArray()`, as written in a
 * type's `collection` option or a page's `collection` front matter.
 *
 * By default a query finds published, public entries of every type,
 * leaving out landing pages (a type folder's `index` file), in file-name
 * order. Naming entries also finds unlisted and hidden ones, as 1.x did,
 * and naming `index` finds landing pages.
 *
 * Every condition must hold. For "this or that", `either()` takes
 * alternatives, each built from `Query::condition()`, and an entry must
 * match at least one of them:
 *
 *     $query->either(
 *         static fn (Query $q): Query => $q->status(Status::Draft),
 *         static fn (Query $q): Query => $q->whereTerm('author', 'jane')
 *     );
 *
 * A query made by a runner (the repository's `query()`) can run itself
 * with `get()`, `first()`, `count()`, and `paginate()`.
 */
final readonly class Query
{
	/**
	 * The number of entries `fromArray()` returns when `number` isn't set,
	 * as in 1.x.
	 */
	public const int DEFAULT_NUMBER = 10;

	/**
	 * The date parts a query can match, coarsest first.
	 *
	 * @var list<string>
	 */
	public const array DATE_PARTS = ['year', 'month', 'day', 'hour', 'minute', 'second'];

	/**
	 * The 1.x and 2.x argument names `fromArray()` accepts.
	 *
	 * @var list<string>
	 */
	private const array ARGUMENTS = [
		'type', 'path', 'names', 'slug', 'names_exclude', 'number', 'offset', 'order', 'orderby', 'author',
		'meta_key', 'meta_value', 'year', 'month', 'day', 'hour', 'minute', 'second', 'noindex', 'nocontent',
		'status', 'visibility', 'terms', 'locale'
	];

	/**
	 * @param list<string>                     $types         Type names; none means every type.
	 * @param ?string                          $directory     The folder entries are listed in (1.x's `path`).
	 * @param list<string>                     $names         Slugs to find.
	 * @param list<string>                     $excludedNames Slugs to leave out.
	 * @param ?int                             $limit         How many entries; `null` for all.
	 * @param int                              $offset        How many matching entries to skip.
	 * @param string                           $orderBy       `filename`, `published`, `updated`, `title`, `slug`, `status`, `author`, a field, or a taxonomy.
	 * @param Order                            $order         The sort direction.
	 * @param list<array{string, list<string>}> $terms        Taxonomy and slugs; an entry needs one of the slugs for each.
	 * @param ?string                          $metaKey       A field entries must have.
	 * @param ?string                          $metaValue     A value (compared as a slug) the field must hold.
	 * @param array<string, int>               $date          Published date parts to match, in the site timezone.
	 * @param list<Status>                     $statuses      Statuses to find.
	 * @param ?list<Visibility>                $visibilities  Visibilities to find; `null` for the default.
	 * @param bool                             $landing       Whether landing pages are found.
	 * @param ?string                          $locale        A locale to limit entries to.
	 * @param ?string                          $search        Text the title or source path must contain, in any case.
	 * @param list<list<Query>>                $alternatives  Groups of alternatives; an entry must match one in each group.
	 * @param ?int                             $updatedSince  A Unix time entries must have been updated at or after.
	 * @param list<string>                     $excludedDirectories Folders whose entries are left out.
	 */
	public function __construct(
		public array $types = [],
		public ?string $directory = null,
		public array $names = [],
		public array $excludedNames = [],
		public ?int $limit = null,
		public int $offset = 0,
		public string $orderBy = 'filename',
		public Order $order = Order::Asc,
		public array $terms = [],
		public ?string $metaKey = null,
		public ?string $metaValue = null,
		public array $date = [],
		public array $statuses = [Status::Published],
		public ?array $visibilities = null,
		public bool $landing = false,
		public ?string $locale = null,
		public ?string $search = null,
		public array $alternatives = [],
		public ?int $updatedSince = null,
		public array $excludedDirectories = [],
		private ?QueryRunner $runner = null
	) {}

	/**
	 * Returns a query that matches every entry, whatever its status,
	 * visibility, or type, landing pages included: the base an
	 * `either()` alternative adds its conditions to.
	 */
	public static function condition(): self
	{
		return new self()->any();
	}

	/**
	 * Builds a query from 1.x query arguments: `type`, `path`, `names` (or
	 * `slug`), `names_exclude`, `number` (10 by default; zero or less for
	 * all), `offset`, `order`, `orderby` (`date` means `published`),
	 * `author`, `meta_key` and `meta_value`, `year` through `second`, and
	 * `noindex`. 2.x adds `status`, `visibility`, `terms` (taxonomy names
	 * to slugs), and `locale`.
	 *
	 * @param  array<array-key, mixed> $arguments
	 * @throws InvalidQuery
	 */
	public static function fromArray(array $arguments, ?QueryRunner $runner = null): self
	{
		$unknown = array_diff(array_map(strval(...), array_keys($arguments)), self::ARGUMENTS);

		if ($unknown !== []) {
			throw new InvalidQuery(sprintf('Unknown query arguments: %s.', implode(', ', $unknown)));
		}

		$query = new self(runner: $runner, limit: self::DEFAULT_NUMBER);

		if (isset($arguments['type'])) {
			$query = $query->type(...self::strings($arguments['type'], 'type'));
		}

		if (isset($arguments['path'])) {
			$query = $query->in(self::string($arguments['path'], 'path'));
		}

		$names = $arguments['names'] ?? $arguments['slug'] ?? null;

		if ($names !== null) {
			$query = $query->names(...self::strings($names, isset($arguments['names']) ? 'names' : 'slug'));
		}

		if (isset($arguments['names_exclude'])) {
			$query = $query->exceptNames(...self::strings($arguments['names_exclude'], 'names_exclude'));
		}

		if (isset($arguments['number'])) {
			$number = self::int($arguments['number'], 'number');
			$query  = $query->limit($number > 0 ? $number : null);
		}

		if (isset($arguments['offset'])) {
			$query = $query->offset(self::int($arguments['offset'], 'offset'));
		}

		$orderBy = isset($arguments['orderby']) ? self::string($arguments['orderby'], 'orderby') : $query->orderBy;
		$order   = isset($arguments['order'])
			? Order::tryFrom(strtolower(self::string($arguments['order'], 'order')))
				?? throw new InvalidQuery('Query argument "order" must be "asc" or "desc".')
			: $query->order;

		$query = $query->orderBy($orderBy === 'date' ? 'published' : $orderBy, $order);

		foreach (isset($arguments['author']) ? self::strings($arguments['author'], 'author') : [] as $author) {
			$query = $query->whereTerm('author', $author);
		}

		foreach (self::map($arguments['terms'] ?? [], 'terms') as $taxonomy => $slugs) {
			$query = $query->whereTerm($taxonomy, ...self::strings($slugs, "terms.{$taxonomy}"));
		}

		if (isset($arguments['meta_key'])) {
			$key   = self::string($arguments['meta_key'], 'meta_key');
			$query = $query->where($key === 'date' ? 'published' : $key, isset($arguments['meta_value']) ? self::string($arguments['meta_value'], 'meta_value') : null);
		}

		$date = [];

		foreach (self::DATE_PARTS as $part) {
			if (isset($arguments[$part])) {
				$date[$part] = self::int($arguments[$part], $part);
			}
		}

		if ($date !== []) {
			$query = clone($query, ['date' => $date]);
		}

		if (isset($arguments['noindex'])) {
			$query = $query->withLanding(! self::bool($arguments['noindex'], 'noindex'));
		}

		if (isset($arguments['status'])) {
			$query = $query->status(...array_map(
				static fn (string $status): Status => Status::tryFrom($status)
					?? throw new InvalidQuery(sprintf('Query argument "status" has an unknown status "%s".', $status)),
				self::strings($arguments['status'], 'status')
			));
		}

		if (isset($arguments['visibility'])) {
			$query = $query->visibility(...array_map(
				static fn (string $visibility): Visibility => Visibility::tryFrom($visibility)
					?? throw new InvalidQuery(sprintf('Query argument "visibility" has an unknown visibility "%s".', $visibility)),
				self::strings($arguments['visibility'], 'visibility')
			));
		}

		if (isset($arguments['locale'])) {
			$query = $query->locale(self::string($arguments['locale'], 'locale'));
		}

		return $query;
	}

	/**
	 * Returns a copy limited to entries of these types.
	 */
	#[NoDiscard]
	public function type(string ...$types): self
	{
		return clone($this, ['types' => array_values(array_unique($types))]);
	}

	/**
	 * Returns a copy limited to entries listed in a folder under the
	 * content root (1.x's `path`). A bundle is listed in its folder's
	 * parent.
	 */
	#[NoDiscard]
	public function in(?string $directory): self
	{
		return clone($this, ['directory' => $directory === null ? null : trim($directory, '/.')]);
	}

	/**
	 * Returns a copy that leaves out entries listed in these folders
	 * under the content root (each folder exactly, not its subfolders).
	 */
	#[NoDiscard]
	public function exceptIn(string ...$directories): self
	{
		return clone($this, ['excludedDirectories' => array_values(array_unique(array_map(static fn (string $directory): string => trim($directory, '/.'), $directories)))]);
	}

	/**
	 * Returns a copy that finds only entries with these slugs.
	 */
	#[NoDiscard]
	public function names(string ...$names): self
	{
		return clone($this, ['names' => array_values(array_unique($names))]);
	}

	/**
	 * Returns a copy that leaves out entries with these slugs.
	 */
	#[NoDiscard]
	public function exceptNames(string ...$names): self
	{
		return clone($this, ['excludedNames' => array_values(array_unique($names))]);
	}

	/**
	 * Returns a copy that returns at most `$limit` entries, or all of
	 * them for `null`.
	 */
	#[NoDiscard]
	public function limit(?int $limit): self
	{
		return clone($this, ['limit' => $limit === null ? null : max(0, $limit)]);
	}

	/**
	 * Returns a copy that skips the first `$offset` matching entries.
	 */
	#[NoDiscard]
	public function offset(int $offset): self
	{
		return clone($this, ['offset' => max(0, $offset)]);
	}

	/**
	 * Returns a copy sorted by `filename` (the source path), `published`,
	 * `updated`, `title`, `slug`, `status` (as it is now, so a scheduled
	 * entry sorts as `scheduled`), `author`, any field, or else a
	 * taxonomy's first term. Entries without the value sort as lowest;
	 * ties keep file-name order.
	 */
	#[NoDiscard]
	public function orderBy(string $key, Order $order = Order::Asc): self
	{
		return clone($this, ['orderBy' => $key, 'order' => $order]);
	}

	/**
	 * Returns a copy limited to entries with one of these terms of a
	 * taxonomy. Each call adds a condition every entry must meet.
	 */
	#[NoDiscard]
	public function whereTerm(string $taxonomy, string ...$slugs): self
	{
		return clone($this, ['terms' => [...$this->terms, [$taxonomy, array_values(array_unique($slugs))]]]);
	}

	/**
	 * Returns a copy whose term conditions and sort on one taxonomy name
	 * read another, in its alternatives too. The repository uses it so
	 * 1.x's `author` (the argument, `whereAuthor()`, and sorting) reads
	 * the site's profiles type, whatever it's named (D-351).
	 */
	#[NoDiscard]
	public function withTaxonomyRenamed(string $from, string $to): self
	{
		return clone($this, [
			'terms'        => array_map(static fn (array $term): array => [$term[0] === $from ? $to : $term[0], $term[1]], $this->terms),
			'orderBy'      => $this->orderBy === $from ? $to : $this->orderBy,
			'alternatives' => array_map(
				static fn (array $group): array => array_map(static fn (self $alternative): self => $alternative->withTaxonomyRenamed($from, $to), $group),
				$this->alternatives
			)
		]);
	}

	/**
	 * Returns a copy limited to entries with the author (all of them, when
	 * several are given).
	 */
	#[NoDiscard]
	public function whereAuthor(string ...$authors): self
	{
		$query = $this;

		foreach ($authors as $author) {
			$query = $query->whereTerm('author', $author);
		}

		return $query;
	}

	/**
	 * Returns a copy limited to entries that have a field (1.x's
	 * `meta_key`), and, with a value, whose field holds it (`meta_value`,
	 * compared as a slug).
	 */
	#[NoDiscard]
	public function where(string $key, ?string $value = null): self
	{
		return clone($this, ['metaKey' => $key, 'metaValue' => $value]);
	}

	/**
	 * Returns a copy limited to entries published in a period, read in the
	 * site timezone. Parts left `null` match anything.
	 */
	#[NoDiscard]
	public function date(
		?int $year = null,
		?int $month = null,
		?int $day = null,
		?int $hour = null,
		?int $minute = null,
		?int $second = null
	): self {
		$parts = compact('year', 'month', 'day', 'hour', 'minute', 'second');

		return clone($this, ['date' => array_filter($parts, static fn (?int $part): bool => $part !== null)]);
	}

	/**
	 * Returns a copy that finds entries with these statuses.
	 */
	#[NoDiscard]
	public function status(Status ...$statuses): self
	{
		return clone($this, ['statuses' => array_values(array_unique($statuses, SORT_REGULAR))]);
	}

	/**
	 * Returns a copy that finds entries with these visibilities.
	 */
	#[NoDiscard]
	public function visibility(Visibility ...$visibilities): self
	{
		return clone($this, ['visibilities' => array_values(array_unique($visibilities, SORT_REGULAR))]);
	}

	/**
	 * Returns a copy that finds entries whatever their status and
	 * visibility, landing pages included.
	 */
	#[NoDiscard]
	public function any(): self
	{
		return clone($this, ['statuses' => Status::cases(), 'visibilities' => Visibility::cases(), 'landing' => true]);
	}

	/**
	 * Returns a copy that does, or doesn't, find landing pages.
	 */
	#[NoDiscard]
	public function withLanding(bool $landing = true): self
	{
		return clone($this, ['landing' => $landing]);
	}

	/**
	 * Returns a copy limited to a locale, or to any locale for `null`.
	 */
	#[NoDiscard]
	public function locale(?string $locale): self
	{
		return clone($this, ['locale' => $locale]);
	}

	/**
	 * Returns a copy limited to entries updated at or after a Unix time,
	 * or updated any time for `null`.
	 */
	#[NoDiscard]
	public function updatedSince(?int $time): self
	{
		return clone($this, ['updatedSince' => $time]);
	}

	/**
	 * Returns a copy limited to entries whose title or source path
	 * contains the text, in any case. Empty text (after trimming) matches
	 * everything.
	 */
	#[NoDiscard]
	public function search(?string $text): self
	{
		$text = $text === null ? '' : trim($text);

		return clone($this, ['search' => $text === '' ? null : $text]);
	}

	/**
	 * Returns a copy limited to entries that match at least one of the
	 * alternatives. Each alternative gets `Query::condition()` and returns
	 * it with conditions added; only its conditions count, not its order,
	 * limit, or offset. Each call adds a group every entry must match one
	 * of, so no alternatives at all match nothing.
	 *
	 * @param Closure(Query): Query ...$alternatives
	 */
	#[NoDiscard]
	public function either(Closure ...$alternatives): self
	{
		$group = array_map(static fn (Closure $alternative): self => $alternative(self::condition()), array_values($alternatives));

		return clone($this, ['alternatives' => [...$this->alternatives, $group]]);
	}

	/**
	 * Returns the visibilities the query finds: those set, or public
	 * entries only unless the query names entries.
	 *
	 * @return list<Visibility>
	 */
	public function visibilities(): array
	{
		return $this->visibilities ?? ($this->names === [] ? [Visibility::Public] : Visibility::cases());
	}

	/**
	 * Returns whether the query finds landing pages.
	 */
	public function findsLanding(): bool
	{
		return $this->landing || in_array('index', $this->names, true);
	}

	/**
	 * Runs the query.
	 *
	 * @throws InvalidQuery When the query has no runner.
	 */
	public function get(): EntryCollection
	{
		return $this->runner()->get($this);
	}

	/**
	 * Returns the first entry the query finds.
	 *
	 * @throws InvalidQuery When the query has no runner.
	 */
	public function first(): ?Entry
	{
		return $this->runner()->get($this->limit(1))->first();
	}

	/**
	 * Returns how many entries match, ignoring the limit and offset.
	 *
	 * @throws InvalidQuery When the query has no runner.
	 */
	public function count(): int
	{
		return $this->runner()->count($this);
	}

	/**
	 * Returns one page of entries.
	 *
	 * @throws InvalidQuery When the query has no runner.
	 */
	public function paginate(int $perPage, int $page = 1): Paginator
	{
		return $this->runner()->paginate($this, $perPage, $page);
	}

	/**
	 * Returns the runner.
	 *
	 * @throws InvalidQuery
	 */
	private function runner(): QueryRunner
	{
		return $this->runner ?? throw new InvalidQuery('This query has no repository to run it; use the repository\'s query().');
	}

	/**
	 * @throws InvalidQuery
	 */
	private static function string(mixed $value, string $name): string
	{
		return is_string($value) || is_int($value) || is_float($value)
			? (string) $value
			: throw new InvalidQuery(sprintf('Query argument "%s" must be text.', $name));
	}

	/**
	 * Reads a string, or a list of them.
	 *
	 * @return list<string>
	 * @throws InvalidQuery
	 */
	private static function strings(mixed $value, string $name): array
	{
		$values = is_array($value) ? $value : [$value];

		if (! array_is_list($values)) {
			throw new InvalidQuery(sprintf('Query argument "%s" must be text or a list.', $name));
		}

		return array_map(static fn (mixed $item): string => self::string($item, $name), $values);
	}

	/**
	 * @throws InvalidQuery
	 */
	private static function int(mixed $value, string $name): int
	{
		return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)
			? (int) $value
			: throw new InvalidQuery(sprintf('Query argument "%s" must be a whole number.', $name));
	}

	/**
	 * @throws InvalidQuery
	 */
	private static function bool(mixed $value, string $name): bool
	{
		return is_bool($value) ? $value : throw new InvalidQuery(sprintf('Query argument "%s" must be true or false.', $name));
	}

	/**
	 * @return array<string, mixed>
	 * @throws InvalidQuery
	 */
	private static function map(mixed $value, string $name): array
	{
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new InvalidQuery(sprintf('Query argument "%s" must be a map.', $name));
		}

		$map = [];

		foreach ($value as $key => $item) {
			$map[(string) $key] = $item;
		}

		return $map;
	}
}
