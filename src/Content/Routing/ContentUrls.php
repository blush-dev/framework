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

use Blush\Content\Entry\Entry;
use Blush\Content\Type\ArchiveGranularity;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
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
 *   with `single.paged`.
 * - Entries of types without routing (pages, and types with
 *   `routing: false`) live at their folder path: `/about/biography`.
 * - A landing page is its type's collection. The home type's collection
 *   is `/`, paged as `/page/{page}`.
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

	public function __construct(
		private ContentTypes $types,
		private RouteConfig $routes,
		private AppConfig $app
	) {}

	/**
	 * Returns an entry's URL path.
	 */
	public function entry(Entry $entry): ?string
	{
		$type = $entry->type;

		if (! $entry->isRoutable() || ! $type->public) {
			return null;
		}

		if (! $type->hasRouting()) {
			return $this->routes->canonicalPath('/' . trim("{$type->path}/{$entry->key}", '/'));
		}

		if ($entry->landing) {
			return $this->collection($type);
		}

		if ($type->taxonomy) {
			return $this->term($type, $entry->key);
		}

		return $this->build($type->routePattern('single'), $this->singleParams($entry));
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
			? $this->build($type->routePattern('collection.paged'), ['page' => (string) $page])
			: $this->build($type->routePattern('collection'), []);
	}

	/**
	 * Returns a taxonomy term's URL path, or a later page's.
	 */
	public function term(ContentType $taxonomy, string $slug, int $page = 1): ?string
	{
		return $page > 1
			? $this->build($taxonomy->routePattern('single.paged'), ['name' => $slug, 'page' => (string) $page])
			: $this->build($taxonomy->routePattern('single'), ['name' => $slug]);
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
			return $type->taxonomy ? $this->build($type->routePattern(str_replace('collection.', 'single.', $key)), ['name' => $term]) : null;
		}

		if ($type->name === $this->types->home) {
			$path = $type->routing === false ? null : $type->routing->path($key);

			return $path === null ? null : $this->build('/' . $path, []);
		}

		return $this->build($type->routePattern($key), []);
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
		$levels = array_map(static fn (ArchiveGranularity $granularity): string => $granularity->value, $type->archives->levels());

		if ($level === null || ! in_array($level, $levels, true)) {
			return null;
		}

		$values = [];

		foreach ($parts as $part => $value) {
			$values[$part] = str_pad((string) $value, $part === 'year' ? 4 : 2, '0', STR_PAD_LEFT);
		}

		return $page > 1
			? $this->build($type->routePattern("collection.{$level}.paged"), [...$values, 'page' => (string) $page])
			: $this->build($type->routePattern("collection.{$level}"), $values);
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
	private function build(?string $pattern, array $values): ?string
	{
		if ($pattern === null) {
			return null;
		}

		try {
			$parsed = RoutePattern::parse($pattern);

			return $this->routes->canonicalPath(
				RoutePattern::parse($pattern, array_intersect_key(self::CONSTRAINTS, array_flip($parsed->params)))->build($values)
			);
		} catch (InvalidRoute | UrlGenerationException) {
			return null;
		}
	}
}
