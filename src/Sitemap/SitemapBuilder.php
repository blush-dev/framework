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
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\PeopleArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Taxonomy;

/**
 * Lists the site's URLs for sitemaps: one sitemap per public type whose
 * `sitemap` option is on (D-078), and an index of them.
 *
 * A type's sitemap holds its collection page (when it has a landing page
 * or lists something), then its listed entries
 * (published, public, not landing pages) that have URLs, with their
 * `updated` dates, then each people field's list and person archives
 * (D-351). The profiles type's holds each profile's page, real or
 * virtual (`PeopleArchives::profiles()`). A taxonomy's holds its terms that list entries
 * (virtual terms included), by slug, so empty archives stay out. The type the root
 * `index.md` belongs to also holds `/`, when the homepage isn't a type's
 * collection.
 */
final readonly class SitemapBuilder
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private PeopleArchives $archives
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
	 * Returns the index: each type's sitemap URL path and its latest
	 * change, leaving out types with no URLs.
	 *
	 * @return list<array{string, ?DateTimeImmutable}>
	 */
	public function index(): array
	{
		$sitemaps = [];

		foreach ($this->types() as $name => $type) {
			$urls = $this->urls($type);

			if ($urls === []) {
				continue;
			}

			$dates      = array_filter(array_map(static fn (SitemapUrl $url): ?DateTimeImmutable => $url->lastmod, $urls));
			$sitemaps[] = ["/sitemap/{$name}", $dates === [] ? null : max($dates)];
		}

		return $sitemaps;
	}

	/**
	 * Returns a type's URLs.
	 *
	 * @return list<SitemapUrl>
	 */
	public function urls(ContentType $type): array
	{
		$urls = [];

		if ($type instanceof Profiles) {
			foreach ($this->archives->profiles() as $profile) {
				$url = $this->urls->profile($profile->slug);

				if ($url !== null) {
					$urls[$url] ??= new SitemapUrl($this->urls->absolute($url), $profile->isVirtual() ? null : $profile->updated);
				}
			}

			return array_values($urls);
		}

		$landing = $this->content->named($type->name, '');
		$landing = $landing !== null && $landing->isPublished() && $landing->isRoutable() ? $landing : null;

		if ($this->types->home === null && $type->name === $this->types->forFile('index.md')->name) {
			$urls['/'] = new SitemapUrl($this->urls->absolute('/'), $landing?->updated);
		}

		$collection = $this->urls->collection($type);

		// An empty listing (no landing page, nothing listed) stays out.
		if ($collection !== null && ($landing !== null || $this->content->query()->type($type->name)->count() > 0)) {
			$urls[$collection] = new SitemapUrl($this->urls->absolute($collection), $landing?->updated);
		}

		if ($type instanceof Taxonomy) {
			$slugs = array_map(strval(...), array_keys($this->content->termCounts($type->name)));
			sort($slugs);

			foreach ($slugs as $slug) {
				$url  = $this->urls->term($type, $slug);
				$term = $this->content->term($type->name, $slug);

				if ($url !== null) {
					$urls[$url] ??= new SitemapUrl($this->urls->absolute($url), $term === null || $term->isVirtual() ? null : $term->updated);
				}
			}

			return array_values($urls);
		}

		foreach ($this->content->query()->type($type->name)->get() as $entry) {
			$this->add($urls, $entry);
		}

		$this->addPeople($urls, $type);

		return array_values($urls);
	}

	/**
	 * Adds each of a type's people fields' lists and person archives
	 * (D-351), when they have any.
	 *
	 * @param array<string, SitemapUrl> $urls
	 */
	private function addPeople(array &$urls, ContentType $type): void
	{
		foreach ($type->archivedPeople() as $field) {
			$people = $this->urls->hasArchive($type, $field) ? $this->archives->credited($type, $field) : [];
			$list   = $people === [] ? null : $this->urls->people($type, $field);

			if ($list !== null) {
				$urls[$list] ??= new SitemapUrl($this->urls->absolute($list));
			}

			foreach ($people as $person) {
				$url = $this->urls->person($type, $field, $person->slug);

				if ($url !== null) {
					$urls[$url] ??= new SitemapUrl($this->urls->absolute($url), $person->isVirtual() ? null : $person->updated);
				}
			}
		}
	}

	/**
	 * Adds an entry's URL, once.
	 *
	 * @param array<string, SitemapUrl> $urls
	 */
	private function add(array &$urls, Entry $entry): void
	{
		$url = $this->urls->entry($entry);

		if ($url !== null) {
			$urls[$url] ??= new SitemapUrl($this->urls->absolute($url), $entry->updated);
		}
	}
}
