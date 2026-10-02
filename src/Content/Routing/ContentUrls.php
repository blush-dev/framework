<?php

/**
 * Content URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\PeopleField;
use Blush\Content\Type\Taxonomy;
use Blush\Core\AppConfig;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RouteConfig;
use Blush\Routing\RoutePattern;
use Blush\Routing\UrlGenerationException;

/**
 * Builds the URLs of entries, collections, terms, and date archives from
 * the same type routing that `ContentRoutes` registers, without the route
 * table, so redirect sources can use it while the table is compiled.
 *
 * - A routed type's entries use its `single` path, filled from the
 *   entry: `{name}` is the key (the slug, unless the entry sits in a
 *   subfolder of the type's folder, which a one-segment `{name}` can't
 *   hold, so such entries have no URL), `{year}` … `{second}` come from the
 *   published date, and a taxonomy's name (such as `{author}` or
 *   `{category}`) is the entry's first term of it.
 * - A taxonomy's terms use `single` with the term's slug, and are paged
 *   with `single.paged`. A hierarchical taxonomy's `{name}` is the term's
 *   path: its parents' slugs, then its own (`web/web-design/css`,
 *   D-260), constrained by `TERM_PATH`. A term whose parent has no file
 *   sits at the top.
 * - Entries of types without routing (pages, and types with
 *   `urls: false`) live at their folder path: `/about/biography`.
 * - A landing page is its type's collection. The home type's collection
 *   is `/`, paged as `/page/{page}`.
 * - A profile (D-351) is at its type's `single`, `/profiles/{name}`.
 * - Each people field with an archive word lists the people it credits
 *   at `{field}.collection` and has an archive per person at
 *   `{field}.single`, under its type's prefix, even for the home type
 *   (`/blog/authors/jane`).
 *
 * `null` means the thing has no URL: a hidden entry, or values the route
 * can't take.
 */
final readonly class ContentUrls
{
	/**
	 * The constraints content routes put on their parameters.
	 *
	 * @var array<string, string>
	 */
	public const array CONSTRAINTS = [
		'year'   => '\d{4}',
		'month'  => '\d{2}',
		'day'    => '\d{2}',
		'hour'   => '\d{2}',
		'minute' => '\d{2}',
		'second' => '\d{2}',
		'page'   => '\d+'
	];

	/**
	 * The constraint on a hierarchical taxonomy's `{name}`: slugs joined
	 * by `/`. Only a lone first segment may be `page` or `feed`, so a
	 * term's paged and feed routes (`{name}/page/2`, `{name}/feed`) and
	 * the taxonomy's own (`/topics/page/2`) still match; a child term
	 * slugged `page` or `feed` has no URL.
	 */
	public const string TERM_PATH = '(?!(?:page|feed)/)[^/]+(?:/(?!(?:page|feed)(?:/|$))[^/]+)*';

	/**
	 * The date format of each date parameter.
	 *
	 * @var array<string, string>
	 */
	private const array DATE_FORMATS = [
		'year'   => 'Y',
		'month'  => 'm',
		'day'    => 'd',
		'hour'   => 'H',
		'minute' => 'i',
		'second' => 's'
	];

	/**
	 * @param Closure(): ContentRepository $content Deferred: only terms' paths need it.
	 */
	public function __construct(
		private ContentTypes $types,
		private RouteConfig $routes,
		private AppConfig $app,
		#[Defer(ContentRepository::class)] private Closure $content
	) {}

	/**
	 * Returns the constraints a type's route puts on its parameters: the
	 * content constraints, and `TERM_PATH` for a hierarchical taxonomy's
	 * `{name}`.
	 *
	 * @param  list<string>          $params
	 * @return array<string, string>
	 */
	public static function constraints(?ContentType $type, array $params): array
	{
		$constraints = array_intersect_key(self::CONSTRAINTS, array_flip($params));

		if ($type instanceof Taxonomy && $type->hierarchical && in_array('name', $params, true)) {
			$constraints['name'] = self::TERM_PATH;
		}

		return $constraints;
	}

	/**
	 * Returns what a term's `{name}` holds: its slug, after its parents'
	 * for a hierarchical taxonomy.
	 */
	public function termPath(Taxonomy $taxonomy, string $slug): string
	{
		if (! $taxonomy->hierarchical) {
			return $slug;
		}

		$content = ($this->content)();
		$path    = [$slug];
		$key     = $slug;

		while (($key = $content->parentKey($taxonomy->name, $key)) !== null && ! in_array($key, $path, true)) {
			array_unshift($path, $key);
		}

		return implode('/', $path);
	}

	/**
	 * Returns an entry's URL path.
	 */
	public function entry(Entry $entry): ?string
	{
		$type = $entry->type;

		if (! $entry->isRoutable() || ! $type->public) {
			return null;
		}

		if (! $type->hasUrls()) {
			return $type->servedAsPages() ? $this->routes->canonicalPath('/' . trim("{$type->pagePath()}/{$entry->key}", '/')) : null;
		}

		if ($entry->landing) {
			return $this->collection($type);
		}

		if ($type instanceof Taxonomy) {
			return $this->term($type, $entry->key);
		}

		return $this->build($type->routePattern('single'), $this->singleParams($entry), $type);
	}

	/**
	 * Returns a type's collection URL path, or a later page's.
	 */
	public function collection(ContentType $type, int $page = 1): ?string
	{
		if ($type->name === $this->types->home) {
			return $this->routes->canonicalPath($page > 1 ? "/page/{$page}" : '/');
		}

		return $page > 1
			? $this->build($type->routePattern('collection.paged'), ['page' => (string) $page], $type)
			: $this->build($type->routePattern('collection'), [], $type);
	}

	/**
	 * Returns a taxonomy term's URL path, or a later page's.
	 */
	public function term(ContentType $taxonomy, string $slug, int $page = 1): ?string
	{
		$name = $taxonomy instanceof Taxonomy ? $this->termPath($taxonomy, $slug) : $slug;

		return $page > 1
			? $this->build($taxonomy->routePattern('single.paged'), ['name' => $name, 'page' => (string) $page], $taxonomy)
			: $this->build($taxonomy->routePattern('single'), ['name' => $name], $taxonomy);
	}

	/**
	 * Returns a profile's page, or a later page's, or `null` when
	 * profiles have no URLs.
	 */
	public function profile(string $slug, int $page = 1): ?string
	{
		$type = $this->types->profiles();

		if ($type === null || ! $type->public) {
			return null;
		}

		return $page > 1
			? $this->build($type->routePattern('single.paged'), ['name' => $slug, 'page' => (string) $page], $type)
			: $this->build($type->routePattern('single'), ['name' => $slug], $type);
	}

	/**
	 * Returns the URL path of a profile's feed (`$key` is `single.feed`,
	 * `.feed.atom`, or `.feed.json`), or `null` without one.
	 */
	public function profileFeed(string $slug, string $key = 'single.feed'): ?string
	{
		$type = $this->types->profiles();

		return $type !== null && $type->public && $type->hasFeed()
			? $this->build($type->routePattern($key), ['name' => $slug], $type)
			: null;
	}

	/**
	 * Returns the URL path of the list of people a type's field credits,
	 * or `null` when the field has no archives.
	 */
	public function people(ContentType $type, PeopleField $field): ?string
	{
		return $this->hasArchive($type, $field) ? $this->build($type->routePattern("{$field->field}.collection"), [], $type) : null;
	}

	/**
	 * Returns a person's archive URL path under a type's field, or a
	 * later page's, or `null` when the field has no archives.
	 */
	public function person(ContentType $type, PeopleField $field, string $slug, int $page = 1): ?string
	{
		if (! $this->hasArchive($type, $field)) {
			return null;
		}

		return $page > 1
			? $this->build($type->routePattern("{$field->field}.single.paged"), ['profile' => $slug, 'page' => (string) $page], $type)
			: $this->build($type->routePattern("{$field->field}.single"), ['profile' => $slug], $type);
	}

	/**
	 * Returns the URL path of a person's feed under a type's field
	 * (`$suffix` is the feed format's route suffix: `''`, `.atom`, or
	 * `.json`), or `null` when the type has no feed or the field no
	 * archives.
	 */
	public function personFeed(ContentType $type, PeopleField $field, string $slug, string $suffix = ''): ?string
	{
		return $type->hasFeed() && $this->hasArchive($type, $field)
			? $this->build($type->routePattern("{$field->field}.single.feed{$suffix}"), ['profile' => $slug], $type)
			: null;
	}

	/**
	 * Returns where a byline links (D-351): the person's archive under
	 * the entry's type's field, else their profile's page, else `null`.
	 */
	public function byline(Entry $entry, string $field, string $slug): ?string
	{
		$people = $entry->type->peopleField($field);

		return ($people === null ? null : $this->person($entry->type, $people, $slug)) ?? $this->profile($slug);
	}

	/**
	 * Returns whether a type's people field has archives: its own
	 * setting, and a profiles type on the site.
	 */
	public function hasArchive(ContentType $type, PeopleField $field): bool
	{
		return isset($type->archivedPeople()[$field->field]) && $this->types->profiles() !== null;
	}

	/**
	 * Returns a feed's URL path: a type's collection feed, or with
	 * `$term`, a taxonomy term's. `$key` is the route key (`collection.feed`,
	 * `collection.feed.atom`, …; the `collection` part is swapped for
	 * `single` for a term). The home type's collection feeds sit at the
	 * site root (`/feed`, 1.x's home alias).
	 */
	public function feed(ContentType $type, string $key = 'collection.feed', ?string $term = null): ?string
	{
		if (! $type->hasFeed() || ! $type->public) {
			return null;
		}

		if ($term !== null) {
			return $type instanceof Taxonomy ? $this->build($type->routePattern(str_replace('collection.', 'single.', $key)), ['name' => $this->termPath($type, $term)], $type) : null;
		}

		if ($type->name === $this->types->home) {
			$path = $type->urls === false ? null : $type->urls->path($key);

			return $path === null ? null : $this->build('/' . $path, [], $type);
		}

		return $this->build($type->routePattern($key), [], $type);
	}

	/**
	 * Returns a date archive's URL path, or a later page's. `$parts` are
	 * the date parts from the year down, such as `['year' => 2008,
	 * 'month' => 4]`, and must stop at a level the type archives.
	 *
	 * @param array<string, int> $parts
	 */
	public function date(ContentType $type, array $parts, int $page = 1): ?string
	{
		$level  = array_key_last($parts);
		$levels = array_map(static fn (DateArchives $granularity): string => $granularity->value, $type->dateArchives->levels());

		if ($level === null || ! in_array($level, $levels, true)) {
			return null;
		}

		$values = [];

		foreach ($parts as $part => $value) {
			$values[$part] = str_pad((string) $value, $part === 'year' ? 4 : 2, '0', STR_PAD_LEFT);
		}

		return $page > 1
			? $this->build($type->routePattern("collection.{$level}.paged"), [...$values, 'page' => (string) $page], $type)
			: $this->build($type->routePattern("collection.{$level}"), $values, $type);
	}

	/**
	 * Returns a URL path as an absolute URL on the site's origin.
	 */
	public function absolute(string $path): string
	{
		return $this->app->absoluteUrl($path);
	}

	/**
	 * Returns the values an entry fills its `single` route with.
	 *
	 * @return array<string, string>
	 */
	public function singleParams(Entry $entry): array
	{
		$values = ['name' => $entry->key];

		if ($entry->published !== null) {
			foreach (self::DATE_FORMATS as $part => $format) {
				$values[$part] = $entry->published->format($format);
			}
		}

		foreach ($entry->terms as $taxonomy => $slugs) {
			if ($slugs !== []) {
				$values[$taxonomy] = $slugs[0];
			}
		}

		return $values;
	}

	/**
	 * Builds a path from a route pattern, or returns `null` when there's
	 * no pattern or the values don't fit it.
	 *
	 * @param array<string, string> $values
	 */
	private function build(?string $pattern, array $values, ContentType $type): ?string
	{
		if ($pattern === null) {
			return null;
		}

		try {
			$parsed = RoutePattern::parse($pattern);

			return $this->routes->canonicalPath(
				RoutePattern::parse($pattern, self::constraints($type, $parsed->params))->build($values)
			);
		} catch (InvalidRoute | UrlGenerationException) {
			return null;
		}
	}
}
