<?php

/**
 * Content site URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\ProfileList;
use Blush\Content\RelationArchives;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Profiles;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Routing\SiteUrl;
use Blush\Routing\UrlSource;

/**
 * Lists every content URL (D-136, D-476), listings first so they keep
 * their paging:
 *
 * 1. The homepage, paged when a type is the home.
 * 2. Each public, routed type's collection, paged.
 * 3. Each taxonomy's terms with files: those listed entries reference
 *    and the rest, paged.
 * 4. Each date archive level of each type with archives, for every
 *    period a listed entry was published in, paged. Periods whose
 *    listing turns out empty are 404s, and so are skipped.
 * 5. Each relation archive's list and targets' archives (D-596, D-602: a
 *    credit's people), and each profile's page (D-351), paged.
 * 6. Every other published entry with a URL, unlisted ones included.
 *
 * On a multilingual site (D-455), the homepage, collections, terms, and
 * date archives are listed for each language, and step 6 lists every
 * language's entries.
 */
final readonly class ContentSiteUrls implements UrlSource
{
	/**
	 * The date parts of each archive level, from the year down.
	 */
	private const array PARTS = ['year' => 'Y', 'month' => 'n', 'day' => 'j', 'hour' => 'G', 'minute' => 'i', 'second' => 's'];

	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ProfileList $profileList,
		private RelationArchives $relationArchives,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		$types = array_filter($this->types->all(), static fn (ContentType $type): bool => $type->public && $type->hasUrls());

		foreach (array_keys($this->app->languages->all()) as $code) {
			yield from $this->listings($types, $this->app->languages->isDefault($code) ? null : $code);
		}

		foreach ($types as $type) {
			yield from $this->related($type);
		}

		foreach ($this->profileList->profiles() as $profile) {
			$path = $this->urls->profile($profile->slug);

			if ($path !== null) {
				yield new SiteUrl($path, fn (int $page): ?string => $this->urls->profile($profile->slug, $page));
			}
		}

		$entries = $this->content->query()->visibility(Visibility::Public, Visibility::Unlisted)->withLanding()->anyLanguage()->get();

		foreach ($entries as $entry) {
			$path = $entry->type instanceof Profiles ? null : $this->urls->entry($entry);

			if ($path !== null) {
				yield new SiteUrl($path);
			}
		}
	}

	/**
	 * Returns the listings in a language (the default for `null`): the
	 * homepage, and each type's collection, terms, and date archives.
	 *
	 * @param  array<string, ContentType> $types
	 * @return iterable<SiteUrl>
	 */
	private function listings(array $types, ?string $language): iterable
	{
		$home = $this->types->homeType();

		yield $home === null
			? new SiteUrl($this->urls->home($language))
			: new SiteUrl((string) $this->urls->collection($home, 1, $language), fn (int $page): ?string => $this->urls->collection($home, $page, $language));

		foreach ($types as $type) {
			$path = $type instanceof Profiles ? null : $this->urls->collection($type, 1, $language);

			if ($path !== null) {
				yield new SiteUrl($path, fn (int $page): ?string => $this->urls->collection($type, $page, $language));
			}
		}

		foreach ($types as $type) {
			if ($this->types->hasTermPages($type->name)) {
				yield from $this->terms($type, $language);
			}
		}

		foreach ($types as $type) {
			if ($type->dateArchives !== DateArchives::None) {
				yield from $this->dates($type, $language);
			}
		}
	}

	/**
	 * Returns a term type's term archives in a language. Terms are named
	 * by their original's slug (D-455).
	 *
	 * @return iterable<SiteUrl>
	 */
	private function terms(ContentType $taxonomy, ?string $language): iterable
	{
		$files  = $this->content->query()->type($taxonomy->name)->visibility(Visibility::Public, Visibility::Unlisted)->language($language)->get();
		$counts = $this->content->termCounts($this->types->termKeys($taxonomy->name), $this->content->query()->language($language));

		// Another language's terms are those its own entries reference.
		$slugs = [
			...array_map(strval(...), array_keys($language === null ? $counts : array_filter($counts))),
			...array_map(fn (Entry $entry): string => $language === null ? $entry->key : $this->urls->originalKey($entry), iterator_to_array($files, false))
		];

		foreach (array_unique($slugs) as $slug) {
			$path = $this->urls->term($taxonomy, $slug, 1, $language);

			if ($path !== null) {
				yield new SiteUrl($path, fn (int $page): ?string => $this->urls->term($taxonomy, $slug, $page, $language));
			}
		}
	}

	/**
	 * Returns each of a type's relation archives' lists and targets'
	 * archives (D-596).
	 *
	 * @return iterable<SiteUrl>
	 */
	private function related(ContentType $type): iterable
	{
		foreach ($this->types->relationArchives($type) as $relation) {
			$targets = $this->relationArchives->linked($type, $relation);
			$list    = $targets === [] ? null : $this->urls->relatedList($type, $relation);

			if ($list !== null) {
				yield new SiteUrl($list);
			}

			foreach ($targets as $target) {
				$path = $this->urls->related($type, $relation, $target->slug);

				if ($path !== null) {
					yield new SiteUrl($path, fn (int $page): ?string => $this->urls->related($type, $relation, $target->slug, $page));
				}
			}
		}
	}


	/**
	 * Returns a type's date archives, every level of each.
	 *
	 * @return iterable<SiteUrl>
	 */
	private function dates(ContentType $type, ?string $language): iterable
	{
		$seen = [];

		foreach ($this->content->query()->type($type->listedType())->language($language)->get() as $entry) {
			if ($entry->published === null) {
				continue;
			}

			$parts = [];

			foreach ($type->dateArchives->levels() as $level) {
				$parts[$level->value] = (int) $entry->published->format(self::PARTS[$level->value] ?? 'Y');
				$key                  = implode('-', $parts);

				if (isset($seen[$key])) {
					continue;
				}

				$seen[$key] = true;
				$current    = $parts;
				$path       = $this->urls->date($type, $current, 1, $language);

				if ($path !== null) {
					yield new SiteUrl($path, fn (int $page): ?string => $this->urls->date($type, $current, $page, $language));
				}
			}
		}
	}
}
