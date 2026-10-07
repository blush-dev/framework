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
use Blush\Content\Relation\Relation;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\PeopleField;
use Blush\Content\Type\Profiles;
use Blush\Core\AppConfig;
use Blush\Markdown\MarkdownException;

/**
 * Builds feeds from content (D-029), with 1.x's defaults (D-078): a
 * type's feed lists the type its listing lists, newest file first, then
 * the feed's own `listing` applies; a term's feed lists its relation's
 * `types`, the same way.
 *
 * Item categories are the terms of the feed's `categories` term type, or
 * of every term type when it has none; authors are the names of the
 * profiles the item's type's first people field credits (D-351), its
 * main byline.
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
	 * Builds a type's collection feed (the homepage's for the home type).
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
			$this->query($type, ['type' => $type->listedType()])
		);
	}

	/**
	 * Builds a term's feed: the entries filed under it, of its relation's
	 * types (D-593).
	 *
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 */
	public function term(ContentType $taxonomy, Entry $term, FeedFormat $format): Feed
	{
		$types = $this->types->termArguments($taxonomy->name)['type'] ?? [];
		$query = $this->query($taxonomy, $types === [] ? [] : ['type' => $types])
			->whereAnyTerm($this->types->termKeys($taxonomy->name), $term->slug);

		return $this->feed(
			$format,
			$term->title,
			$this->urls->term($taxonomy, $term->slug) ?? '/',
			$this->urls->feed($taxonomy, 'collection.feed' . $format->routeSuffix(), $term->slug) ?? '/',
			$term,
			$query
		);
	}

	/**
	 * Builds a person's feed under a type's people field (D-351): the
	 * type's entries crediting them there.
	 *
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 */
	public function person(ContentType $type, PeopleField $field, Entry $profile, FeedFormat $format): Feed
	{
		$query = $this->query($type, ['type' => $type->name])->whereTerm($field->termKey($profile->type->name), $profile->slug);

		return $this->feed(
			$format,
			"{$profile->title} | {$field->plural} | {$type->labels->plural}",
			$this->urls->person($type, $field, $profile->slug) ?? '/',
			$this->urls->personFeed($type, $field, $profile->slug, $format->routeSuffix()) ?? '/',
			$profile,
			$query
		);
	}

	/**
	 * Builds a target's feed under a type's relation with an archive word
	 * (D-596): the type's entries linking to it there.
	 *
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 */
	public function related(ContentType $type, Relation $relation, Entry $target, FeedFormat $format): Feed
	{
		$query = $this->query($type, ['type' => $type->name])->whereTerm((string) $relation->termKey(), $target->slug);

		return $this->feed(
			$format,
			"{$target->title} | {$type->labels->plural}",
			$this->urls->related($type, $relation, $target->slug) ?? '/',
			$this->urls->relatedFeed($type, $relation, $target->slug, $format->routeSuffix()) ?? '/',
			$target,
			$query
		);
	}

	/**
	 * Builds a profile's feed (D-351): every type's entries crediting
	 * them.
	 *
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 */
	public function profile(Profiles $profiles, Entry $profile, FeedFormat $format): Feed
	{
		$crediting = array_keys($this->types->crediting());
		$query     = $this->query($profiles, ['type' => $crediting === [] ? $profiles->name : $crediting])->whereTerm($profiles->name, $profile->slug);

		return $this->feed(
			$format,
			$profile->title,
			$this->urls->profile($profile->slug) ?? '/',
			$this->urls->profileFeed($profile->slug, 'single.feed' . $format->routeSuffix()) ?? '/',
			$profile,
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
		$arguments = $type->feed === false ? [] : ($type->feed->listing?->arguments() ?? []);

		return Query::fromArray([...$base, 'order' => 'desc', 'orderby' => 'published', 'number' => $this->config->limit, ...$arguments], $this->content);
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
			description: ($about === null ? '' : trim(html_entity_decode(strip_tags($about->excerpt()), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: $this->app->description,
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
		$profiles   = $this->types->profiles()?->name;
		$byline     = array_first($entry->type->people);
		$taxonomies = $feed !== false && $feed->categories !== null
			? [$feed->categories]
			: array_values(array_filter(array_keys($entry->terms), fn (string $taxonomy): bool => $this->types->classification($taxonomy) !== null));

		return new FeedItem(
			entry: $entry,
			title: $entry->title !== '' ? $entry->title : $entry->slug,
			url: $this->urls->absolute($url),
			published: $entry->published ?? $entry->updated,
			updated: $entry->updated,
			content: $this->config->content ? $entry->body() : '',
			summary: $entry->excerpt(),
			authors: $profiles === null || $byline === null ? [] : $this->titles($entry, [$byline->termKey($profiles)], $profiles),
			categories: $this->titles($entry, $taxonomies)
		);
	}

	/**
	 * Returns the titles of an entry's terms in some taxonomies (or under
	 * some people fields' keys, given the profiles type, `$of`).
	 *
	 * @param  list<string> $taxonomies
	 * @return list<string>
	 */
	private function titles(Entry $entry, array $taxonomies, ?string $of = null): array
	{
		$titles = [];

		foreach ($taxonomies as $taxonomy) {
			foreach ($entry->terms($taxonomy) as $slug) {
				$term     = $this->content->term($of ?? $taxonomy, $slug);
				$titles[] = $term !== null && $term->title !== '' ? $term->title : $slug;
			}
		}

		return array_values(array_unique($titles));
	}
}
