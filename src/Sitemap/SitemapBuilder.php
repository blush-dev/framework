<?php

/**
 * Sitemap builder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use DateTimeImmutable;
use Blush\Content\Entries;
use Blush\Content\Query\Query;
use Blush\Content\ProfileList;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Core\AppConfig;
use Blush\Storage\Record\Order;

/**
 * Lists the site's URLs for sitemaps: one sitemap per public type whose
 * `sitemap` option is on (D-078), and an index of them.
 *
 * A type's sitemap holds its collection page (when it has a landing page
 * or lists something), then its listed entries
 * (published, public, not landing pages) that have URLs, with their
 * `updated` dates (a collection's oldest first, so new entries land on
 * its last page, D-911), then each relation archive's list and targets'
 * archives (D-596, D-602). The profiles type's holds each profile's page
 * (`ProfileList::profiles()`). A term type's holds its published
 * terms that list entries, by slug, so empty archives stay out. The type the root
 * `index.md` belongs to also holds `/`, when the homepage isn't a type's
 * collection.
 *
 * A type with more URLs than `SitemapConfig::$perSitemap` is split into
 * pages (D-911): the first at `/sitemap/{type}`, the rest at
 * `/sitemap/{type}/{page}`, each listed in the index. A page builds only
 * the entries on it, and the index builds none (D-912): it counts each
 * type's entries and reads when each last changed
 * (`Query::updatedTimes()`), once a type. A page whose entries have no
 * URLs can hold fewer than the limit.
 */
final readonly class SitemapBuilder
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ProfileList $profileList,
		private RelationArchives $relationArchives,
		private SitemapConfig $config,
		private AppConfig $app
	) {}

	/**
	 * Returns the types with sitemaps, by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function types(): array
	{
		return array_filter($this->types->all(), static fn (ContentType $type): bool => $type->public && $type->sitemap);
	}

	/**
	 * Returns the index: each sitemap's URL path and its latest change, a
	 * path for each page of a type split into pages, leaving out types
	 * with no URLs.
	 *
	 * @return list<array{string, ?DateTimeImmutable}>
	 */
	public function index(): array
	{
		$sitemaps = [];

		foreach ($this->types() as $name => $type) {
			$parts = $this->parts($type);
			$times = $parts->query?->updatedTimes() ?? [];
			$pages = (int) ceil($parts->total() / $this->config->perSitemap);

			for ($page = 1; $page <= $pages; $page++) {
				$sitemaps[] = [$page === 1 ? "/sitemap/{$name}" : "/sitemap/{$name}/{$page}", $this->lastmod($parts, $page, $times)];
			}
		}

		return $sitemaps;
	}

	/**
	 * Returns one page of a type's URLs, or `null` for a page past its
	 * last. The first page is there even for a type with none.
	 *
	 * @return ?list<SitemapUrl>
	 */
	public function page(ContentType $type, int $page): ?array
	{
		$parts = $this->parts($type);

		if ($page < 1 || ($page > 1 && ($page - 1) * $this->config->perSitemap >= $parts->total())) {
			return null;
		}

		[$before, $entries, $after] = $this->slice($parts, $page);

		$urls = [];

		foreach ($before as $url) {
			$urls[$url->loc] ??= $url;
		}

		foreach ($entries?->get() ?? [] as $entry) {
			$url = $this->urls->entry($entry);

			if ($url !== null) {
				$urls[$this->urls->absolute($url)] ??= new SitemapUrl($this->urls->absolute($url), $entry->updated);
			}
		}

		foreach ($after as $url) {
			$urls[$url->loc] ??= $url;
		}

		return array_values($urls);
	}

	/**
	 * Returns a page's latest change: its URLs' before and after the
	 * entries, and its entries', from when each of the type's entries
	 * last changed.
	 *
	 * @param list<?int> $times The type's entries' times, in order.
	 */
	private function lastmod(SitemapParts $parts, int $page, array $times): ?DateTimeImmutable
	{
		[$before, , $after, $first, $last] = $this->slice($parts, $page);

		$entries = array_filter(array_slice($times, $first, max(0, $last - $first)), is_int(...));
		$dates   = array_filter(array_map(static fn (SitemapUrl $url): ?DateTimeImmutable => $url->lastmod, [...$before, ...$after]));

		if ($entries !== []) {
			$dates[] = DateTimeImmutable::createFromTimestamp(max($entries))->setTimezone($this->app->timezone());
		}

		return $dates === [] ? null : max($dates);
	}

	/**
	 * Returns what's on a page: the URLs before the entries, the query for
	 * its entries (`null` when it has none), the URLs after, and the
	 * range of the type's entries it holds (first, and one past last).
	 *
	 * @return array{list<SitemapUrl>, ?Query, list<SitemapUrl>, int, int}
	 */
	private function slice(SitemapParts $parts, int $page): array
	{
		$size  = $this->config->perSitemap;
		$start = ($page - 1) * $size;
		$end   = $start + $size;
		$count = count($parts->before);

		$first = max(0, $start - $count);
		$last  = min($parts->entries, $end - $count);

		return [
			array_slice($parts->before, $start, max(0, min($end, $count) - $start)),
			$parts->query === null || $last <= $first ? null : $parts->query->offset($first)->limit($last - $first),
			array_slice($parts->after, max(0, $start - $count - $parts->entries), max(0, $end - max($start, $count + $parts->entries))),
			$first,
			max($first, $last)
		];
	}

	/**
	 * Returns a type's sitemap in parts: the URLs before its entries, the
	 * query for its entries with how many there are, and the URLs after.
	 * Profiles and term archives are listed in full, before; a type's
	 * entries are counted, never built here.
	 */
	private function parts(ContentType $type): SitemapParts
	{
		$before = [];

		if ($type instanceof Profiles) {
			foreach ($this->profileList->profiles() as $profile) {
				$url = $this->urls->profile($profile->slug);

				if ($url !== null) {
					$before[$url] ??= new SitemapUrl($this->urls->absolute($url), $profile->updated);
				}
			}

			return new SitemapParts(array_values($before));
		}

		$landing = $this->content->named($type->name, '');
		$landing = $landing !== null && $landing->isLinkable() ? $landing : null;

		if ($this->types->home === null && $type->name === $this->types->forFile('index.md')->name) {
			$before['/'] = new SitemapUrl($this->urls->absolute('/'), $landing?->updated);
		}

		$collection = $this->urls->collection($type);
		$query      = $this->content->query()->type($type->name);
		$entries    = $query->count();

		// An empty listing (no landing page, nothing listed) stays out.
		if ($collection !== null && ($landing !== null || $entries > 0)) {
			$before[$collection] = new SitemapUrl($this->urls->absolute($collection), $landing?->updated);
		}

		if ($this->types->hasTermPages($type->name)) {
			$slugs = array_map(strval(...), array_keys($this->content->termCounts($this->types->termKeys($type->name))));
			sort($slugs);

			foreach ($slugs as $slug) {
				$url  = $this->urls->term($type, $slug);
				$term = $this->content->term($type->name, $slug);

				if ($url !== null && $term !== null && $term->isLinkable()) {
					$before[$url] ??= new SitemapUrl($this->urls->absolute($url), $term->updated);
				}
			}

			return new SitemapParts(array_values($before));
		}

		// A collection's oldest first, so its pages stay as they are as it
		// grows; a tree keeps its order (D-911).
		$order = $type instanceof Collection ? ['date', Order::Asc] : $type->order();

		return new SitemapParts(array_values($before), $query->orderBy(...$order), $entries, $this->related($type));
	}

	/**
	 * Returns each of a type's relation archives' lists and targets'
	 * archives (D-596), when they have any.
	 *
	 * @return list<SitemapUrl>
	 */
	private function related(ContentType $type): array
	{
		$urls = [];

		foreach ($this->types->relationArchives($type) as $relation) {
			$targets = $this->relationArchives->linked($type, $relation);
			$list    = $targets === [] ? null : $this->urls->relatedList($type, $relation);

			if ($list !== null) {
				$urls[$list] ??= new SitemapUrl($this->urls->absolute($list));
			}

			foreach ($targets as $target) {
				$url = $this->urls->related($type, $relation, $target->slug);

				if ($url !== null) {
					$urls[$url] ??= new SitemapUrl($this->urls->absolute($url), $target->updated);
				}
			}
		}

		return array_values($urls);
	}
}
