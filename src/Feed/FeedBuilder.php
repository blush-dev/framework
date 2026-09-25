<?php

/**
 * Feed builder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Markdown\MarkdownException;

/**
 * Builds feeds from content (D-029), with 1.x's arguments (D-078): a
 * type's feed lists its entries (or its `collect` type's) newest file
 * first, then the type's `feed.collection` arguments apply; a term's feed
 * lists what its archive lists (`termCollect`), with the same arguments.
 *
 * Item categories are the terms of the type's `feed.taxonomy` (1.x), or
 * of every taxonomy but authors when it has none; authors are the item's
 * author terms.
 */
final readonly class FeedBuilder
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private FeedConfig $config,
		private AppConfig $app,
		private ClockInterface $clock
	) {}

	/**
	 * Builds a type's collection feed (the home page's for the home type).
	 *
	 * @throws InvalidQuery When the feed arguments are invalid.
	 * @throws MarkdownException
	 */
	public function collection(ContentType $type, FeedFormat $format): Feed
	{
		$landing = $this->content->named($type->name, '');
		$landing = $landing !== null && $landing->isPublished() ? $landing : null;
		$home    = $type->name === $this->types->home;
		$title   = $home ? '' : ($landing->title ?? '');

		return $this->feed(
			$format,
			$title !== '' ? $title : ($home ? '' : ucfirst($type->name)),
			$this->urls->collection($type) ?? '/',
			$this->urls->feed($type, 'collection.feed' . $format->routeSuffix()) ?? '/',
			$landing,
			$this->query($type, ['type' => $type->collect === false ? $type->name : $type->collect])
		);
	}

	/**
	 * Builds a taxonomy term's feed.
	 *
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 */
	public function term(ContentType $taxonomy, Entry $term, FeedFormat $format): Feed
	{
		$query = $this->query($taxonomy, $taxonomy->termCollect === null ? [] : ['type' => $taxonomy->termCollect])
			->whereTerm($taxonomy->name, $term->slug);

		return $this->feed(
			$format,
			$term->title,
			$this->urls->term($taxonomy, $term->slug) ?? '/',
			$this->urls->feed($taxonomy, 'collection.feed' . $format->routeSuffix(), $term->slug) ?? '/',
			$term->isVirtual() ? null : $term,
			$query
		);
	}

	/**
	 * Builds a type's feed query.
	 *
	 * @param  array<string, mixed> $base
	 * @throws InvalidQuery
	 */
	private function query(ContentType $type, array $base): Query
	{
		$arguments = $type->feed === false ? [] : $type->feed->collection;

		return Query::fromArray([...$base, 'order' => 'desc', 'orderby' => 'filename', 'number' => $this->config->limit, ...$arguments], $this->content);
	}

	/**
	 * Builds the feed.
	 *
	 * @throws MarkdownException
	 */
	private function feed(FeedFormat $format, string $title, string $link, string $feedUrl, ?Entry $about, Query $query): Feed
	{
		$items   = [];
		$updated = null;

		foreach ($query->get() as $entry) {
			$item = $this->item($entry);

			if ($item !== null) {
				$items[] = $item;
				$updated = $updated === null || $item->updated > $updated ? $item->updated : $updated;
			}
		}

		return new Feed(
			format: $format,
			title: $title === '' ? $this->app->name : "{$title} | {$this->app->name}",
			link: $this->urls->absolute($link),
			feedUrl: $this->urls->absolute($feedUrl),
			updated: $updated ?? $about->updated ?? DateTimeImmutable::createFromInterface($this->clock->now()),
			description: $about === null ? '' : trim(html_entity_decode(strip_tags($about->excerpt()), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
			language: str_replace('_', '-', $this->app->locale),
			items: $items
		);
	}

	/**
	 * Builds an entry's item, or `null` when it has no URL.
	 *
	 * @throws MarkdownException
	 */
	private function item(Entry $entry): ?FeedItem
	{
		$url = $this->urls->entry($entry);

		if ($url === null) {
			return null;
		}

		$feed       = $entry->type->feed;
		$taxonomies = $feed !== false && $feed->taxonomy !== null
			? [$feed->taxonomy]
			: array_values(array_filter(array_keys($entry->terms), static fn (string $taxonomy): bool => $taxonomy !== 'author'));

		return new FeedItem(
			entry: $entry,
			title: $entry->title !== '' ? $entry->title : $entry->slug,
			url: $this->urls->absolute($url),
			published: $entry->published ?? $entry->updated,
			updated: $entry->updated,
			content: $this->config->content ? $entry->body() : '',
			summary: $entry->excerpt(),
			authors: $this->titles($entry, ['author']),
			categories: $this->titles($entry, $taxonomies)
		);
	}

	/**
	 * Returns the titles of an entry's terms in some taxonomies.
	 *
	 * @param  list<string> $taxonomies
	 * @return list<string>
	 */
	private function titles(Entry $entry, array $taxonomies): array
	{
		$titles = [];

		foreach ($taxonomies as $taxonomy) {
			foreach ($entry->terms($taxonomy) as $slug) {
				$term     = $this->content->term($taxonomy, $slug);
				$titles[] = $term !== null && $term->title !== '' ? $term->title : $slug;
			}
		}

		return array_values(array_unique($titles));
	}
}
