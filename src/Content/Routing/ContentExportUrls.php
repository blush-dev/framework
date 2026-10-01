<?php

/**
 * Content export URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Blush\Content\AuthorArchives;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Taxonomy;
use Blush\Content\Visibility;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists every content URL for static export (D-136), listings first so
 * they keep their paging:
 *
 * 1. The home page, paged when a type is the home.
 * 2. Each public, routed type's collection, paged.
 * 3. Each taxonomy's terms: those listed entries reference (virtual ones
 *    included) and those with files, paged.
 * 4. Each date archive level of each type with archives, for every
 *    period a listed entry was published in, paged. Periods whose
 *    listing turns out empty are 404s, and so are skipped.
 * 5. Each type's authors list and author archives (D-329), paged.
 * 6. Every published entry with a URL, unlisted ones included.
 */
final readonly class ContentExportUrls implements UrlSource
{
	/**
	 * The date parts of each archive level, from the year down.
	 */
	private const array PARTS = ['year' => 'Y', 'month' => 'n', 'day' => 'j', 'hour' => 'G', 'minute' => 'i', 'second' => 's'];

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private AuthorArchives $archives
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		$home = $this->types->homeType();

		yield $home === null
			? new ExportUrl('/')
			: new ExportUrl((string) $this->urls->collection($home), fn (int $page): ?string => $this->urls->collection($home, $page));

		$types = array_filter($this->types->all(), static fn (ContentType $type): bool => $type->public && $type->hasUrls());

		foreach ($types as $type) {
			$path = $this->urls->collection($type);

			if ($path !== null) {
				yield new ExportUrl($path, fn (int $page): ?string => $this->urls->collection($type, $page));
			}
		}

		foreach ($types as $type) {
			if ($type instanceof Taxonomy) {
				yield from $this->terms($type);
			}
		}

		foreach ($types as $type) {
			if ($type->dateArchives !== DateArchives::None) {
				yield from $this->dates($type);
			}
		}

		foreach ($types as $type) {
			yield from $this->authors($type);
		}

		$entries = $this->content->query()->visibility(Visibility::Public, Visibility::Unlisted)->withLanding()->get();

		foreach ($entries as $entry) {
			$path = $this->urls->entry($entry);

			if ($path !== null) {
				yield new ExportUrl($path);
			}
		}
	}

	/**
	 * Returns a taxonomy's term archives.
	 *
	 * @return iterable<ExportUrl>
	 */
	private function terms(ContentType $taxonomy): iterable
	{
		$files = $this->content->query()->type($taxonomy->name)->visibility(Visibility::Public, Visibility::Unlisted)->get();
		$slugs = [
			...array_map(strval(...), array_keys($this->content->termCounts($taxonomy->name))),
			...array_map(static fn (Entry $entry): string => $entry->key, iterator_to_array($files, false))
		];

		foreach (array_unique($slugs) as $slug) {
			$path = $this->urls->term($taxonomy, $slug);

			if ($path !== null) {
				yield new ExportUrl($path, fn (int $page): ?string => $this->urls->term($taxonomy, $slug, $page));
			}
		}
	}

	/**
	 * Returns a type's authors list and author archives.
	 *
	 * @return iterable<ExportUrl>
	 */
	private function authors(ContentType $type): iterable
	{
		$authors = $this->urls->hasAuthorArchives($type) ? $this->archives->authors($type) : [];
		$list    = $authors === [] ? null : $this->urls->authors($type);

		if ($list !== null) {
			yield new ExportUrl($list);
		}

		foreach ($authors as $author) {
			$path = $this->urls->author($type, $author->slug);

			if ($path !== null) {
				yield new ExportUrl($path, fn (int $page): ?string => $this->urls->author($type, $author->slug, $page));
			}
		}
	}

	/**
	 * Returns a type's date archives, every level of each.
	 *
	 * @return iterable<ExportUrl>
	 */
	private function dates(ContentType $type): iterable
	{
		$seen = [];

		foreach ($this->content->query()->type($type->listedType())->get() as $entry) {
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
				$path       = $this->urls->date($type, $current);

				if ($path !== null) {
					yield new ExportUrl($path, fn (int $page): ?string => $this->urls->date($type, $current, $page));
				}
			}
		}
	}
}
