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
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\PeopleField;
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
 *   published date, and a term type's name (such as `{author}` or
 *   `{category}`) is the entry's first term of it.
 * - Terms use `single` with the term's slug, and are paged with
 *   `single.paged`. A hierarchical collection's `{name}` is the entry's
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
 * - On a multilingual site (D-455), an entry in a language other than
 *   the default is under its code (`/fr/a-propos`), and so are a
 *   language's collections, terms, and date archives when they're asked
 *   for in it. A term is named by its original's slug, which becomes its
 *   translation's (`music` is `/fr/topics/musique` when
 *   `topics/music.fr.md` has `slug: musique`). Profiles, people
 *   archives, and feeds aren't in other languages yet.
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
	 * The constraint on a hierarchical collection's `{name}`: slugs joined
	 * by `/`. Only a lone first segment may be `page` or `feed`, so a
	 * term's paged and feed routes (`{name}/page/2`, `{name}/feed`) and
	 * the collection's own (`/topics/page/2`) still match; a child term
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
	 * content constraints, and `TERM_PATH` for a hierarchical collection's
	 * `{name}`.
	 *
	 * @param  list<string>          $params
	 * @return array<string, string>
	 */
	public static function constraints(?ContentType $type, array $params): array
	{
		$constraints = array_intersect_key(self::CONSTRAINTS, array_flip($params));

		if ($type instanceof Collection && $type->hierarchical && in_array('name', $params, true)) {
			$constraints['name'] = self::TERM_PATH;
		}

		return $constraints;
	}

	/**
	 * Returns what a term's `{name}` holds: its slug, after its parents'
	 * for a hierarchical collection. In a language other than the
	 * default, each slug is its translation's.
	 */
	public function termPath(ContentType $taxonomy, string $slug, ?string $language = null): string
	{
		$path = [$slug];

		if ($taxonomy instanceof Collection && $taxonomy->hierarchical) {
			$content = ($this->content)();
			$key     = $slug;

			while (($key = $content->parentKey($taxonomy->name, $key)) !== null && ! in_array($key, $path, true)) {
				array_unshift($path, $key);
			}
		}

		return implode('/', array_map(fn (string $key): string => $this->translatedKey($taxonomy->name, $key, $language), $path));
	}

	/**
	 * Returns the key of an entry's original, the default language's
	 * entry it translates, or its own key when it has none (D-455).
	 * Entries name terms by these.
	 */
	public function originalKey(Entry $entry): string
	{
		return ($this->content)()->translation($entry, $this->app->languages->default->code)->key ?? $entry->key;
	}

	/**
	 * Returns the key of the translation, in a language, of the default
	 * language's entry with a key, or the key itself when there's none.
	 */
	public function translatedKey(string $type, string $key, ?string $language): string
	{
		if ($language === null || $this->app->languages->isDefault($language)) {
			return $key;
		}

		$content  = ($this->content)();
		$original = $content->named($type, $key);

		return ($original === null ? null : $content->translation($original, $language)?->key) ?? $key;
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
			return $type->servedAsPages() ? $this->localized($this->routes->canonicalPath('/' . trim("{$type->pagePath()}/{$entry->key}", '/')), $entry->language) : null;
		}

		if ($entry->landing) {
			return $this->collection($type, 1, $entry->language);
		}

		if ($type instanceof Collection && ($type->hierarchical || $this->types->hasTermPages($type->name))) {
			return $this->localized($this->termUrl($type, $this->termPathOf($type, $entry)), $entry->language);
		}

		return $this->localized($this->build($type->routePattern('single'), $this->singleParams($entry), $type), $entry->language);
	}

	/**
	 * Returns a type's collection URL path, or a later page's, in a
	 * language (the default when `null`).
	 */
	public function collection(ContentType $type, int $page = 1, ?string $language = null): ?string
	{
		if ($type->name === $this->types->home) {
			return $this->localized($this->routes->canonicalPath($page > 1 ? "/page/{$page}" : '/'), $language);
		}

		return $this->localized($page > 1
			? $this->build($type->routePattern('collection.paged'), ['page' => (string) $page], $type)
			: $this->build($type->routePattern('collection'), [], $type), $language);
	}

	/**
	 * Returns a term's URL path, or a later page's, by its slug
	 * (the default language's), in a language (the default when `null`).
	 */
	public function term(ContentType $taxonomy, string $slug, int $page = 1, ?string $language = null): ?string
	{
		$name = $this->termPath($taxonomy, $slug, $language);

		return $this->localized($this->termUrl($taxonomy, $name, $page), $language);
	}

	/**
	 * Returns the URL path of the homepage in a language: `/`, or the
	 * language's `/{code}`.
	 */
	public function home(?string $language = null): string
	{
		return $this->localized($this->routes->canonicalPath('/'), $language) ?? '/';
	}

	/**
	 * Returns a URL path under a language's prefix: unchanged for the
	 * default language (or `null`), else under `/{code}`.
	 */
	public function localized(?string $path, ?string $language): ?string
	{
		if ($path === null || $language === null || ! $this->app->languages->isOther($language)) {
			return $path;
		}

		return $this->routes->canonicalPath('/' . $language . ($path === '/' ? '' : $path));
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
			return $this->types->hasTermPages($type->name) ? $this->build($type->routePattern(str_replace('collection.', 'single.', $key)), ['name' => $this->termPath($type, $term)], $type) : null;
		}

		if ($type->name === $this->types->home) {
			$path = $type->urls === false ? null : $type->urls->path($key);

			return $path === null ? null : $this->build('/' . $path, [], $type);
		}

		return $this->build($type->routePattern($key), [], $type);
	}

	/**
	 * Returns a date archive's URL path, or a later page's, in a language
	 * (the default when `null`). `$parts` are the date parts from the
	 * year down, such as `['year' => 2008, 'month' => 4]`, and must stop
	 * at a level the type archives.
	 *
	 * @param array<string, int> $parts
	 */
	public function date(ContentType $type, array $parts, int $page = 1, ?string $language = null): ?string
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

		return $this->localized($page > 1
			? $this->build($type->routePattern("collection.{$level}.paged"), [...$values, 'page' => (string) $page], $type)
			: $this->build($type->routePattern("collection.{$level}"), $values, $type), $language);
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
	 * Returns a term's path, or a later page's, from what its `{name}`
	 * holds.
	 */
	private function termUrl(ContentType $taxonomy, string $name, int $page = 1): ?string
	{
		return $page > 1
			? $this->build($taxonomy->routePattern('single.paged'), ['name' => $name, 'page' => (string) $page], $taxonomy)
			: $this->build($taxonomy->routePattern('single'), ['name' => $name], $taxonomy);
	}

	/**
	 * Returns what a term entry's `{name}` holds: for a translation, the
	 * path of its original's slug in its language, which is its own key
	 * at the end; else its slug after its parents'.
	 */
	private function termPathOf(ContentType $taxonomy, Entry $entry): string
	{
		if (! $this->app->languages->isOther($entry->language)) {
			return $this->termPath($taxonomy, $entry->key);
		}

		$original = ($this->content)()->translation($entry, $this->app->languages->default->code);

		return $original === null ? $entry->key : $this->termPath($taxonomy, $original->key, $entry->language);
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
