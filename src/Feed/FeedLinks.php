<?php

/**
 * Feed links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Closure;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Core\AppConfig;

/**
 * The feeds a page advertises with `<link rel="alternate">`: the home
 * feed on every page, plus the feed of what the page shows (its type's
 * collection, its term's, a target's under a relation archive (a
 * person's under a credit), or a profile's).
 */
final readonly class FeedLinks
{
	public function __construct(
		private ContentTypes $types,
		private ContentUrls $urls,
		private FeedConfig $config,
		private AppConfig $app
	) {}

	/**
	 * Returns a page's feeds: URL path, format, and title.
	 *
	 * @return list<array{string, FeedFormat, string}>
	 */
	public function forPage(ContentPage $page): array
	{
		$links = [];
		$home  = $this->types->homeType();

		if ($home !== null) {
			$this->add($links, $home, $this->app->name);
		}

		$type = $page->type;

		if ($type !== null && $page->kind !== PageKind::Home) {
			if ($page->kind === PageKind::Term && $page->entry !== null) {
				$this->add($links, $type, $page->title, $page->entry->slug);
			} elseif ($page->kind === PageKind::Related && $page->relation !== null && $page->entry !== null) {
				$slug     = $page->entry->slug;
				$relation = $page->relation;

				$this->addFeeds($links, "{$page->title} | {$type->labels->plural}", fn (FeedFormat $format): ?string => $this->urls->relatedFeed($type, $relation, $slug, $format->routeSuffix()));
			} elseif ($page->kind === PageKind::Profile && $page->target !== null) {
				$this->addFeeds($links, $page->title, fn (FeedFormat $format): ?string => $this->urls->profileFeed($page->target->slug, 'single.feed' . $format->routeSuffix()));
			} elseif ($type->hasUrls() && ! $type instanceof Profiles) {
				$this->add($links, $type, $page->kind === PageKind::Collection && $page->title !== '' ? $page->title : ucfirst($type->name));
			}
		}

		return array_values($links);
	}

	/**
	 * Adds the feeds a callback gives the URL of, per format.
	 *
	 * @param array<string, array{string, FeedFormat, string}> $links
	 * @param Closure(FeedFormat): ?string                    $feed
	 */
	private function addFeeds(array &$links, string $title, Closure $feed): void
	{
		foreach ($this->config->formats as $format) {
			$url = $feed($format);

			if ($url !== null) {
				$links[$url] ??= [$url, $format, "{$title} ({$format->label()})"];
			}
		}
	}

	/**
	 * Adds a type's (or term's) feeds, keyed by URL; a feed already
	 * listed keeps its title (the home type's feed is the home feed).
	 *
	 * @param array<string, array{string, FeedFormat, string}> $links
	 */
	private function add(array &$links, ContentType $type, string $title, ?string $term = null): void
	{
		foreach ($this->config->formats as $format) {
			$url = $this->urls->feed($type, 'collection.feed' . $format->routeSuffix(), $term);

			if ($url !== null) {
				$links[$url] ??= [$url, $format, "{$title} ({$format->label()})"];
			}
		}
	}
}
