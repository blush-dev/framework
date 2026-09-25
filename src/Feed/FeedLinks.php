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

use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * The feeds a page advertises with `<link rel="alternate">`: the home
 * feed on every page, plus the feed of what the page shows (its type's
 * collection, or its term's).
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
			} elseif ($type->hasRouting()) {
				$this->add($links, $type, $page->kind === PageKind::Collection && $page->title !== '' ? $page->title : ucfirst($type->name));
			}
		}

		return array_values($links);
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
