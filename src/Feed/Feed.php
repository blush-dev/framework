<?php

/**
 * Feed.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A feed, ready for its template (`feed-{format}`): the channel's title,
 * description, site and feed URLs (absolute), language, last update, and
 * items. `jsonFeed()` gives the JSON Feed 1.1 document, so the JSON
 * template stays one line.
 */
final readonly class Feed
{
	/**
	 * @param list<FeedItem> $items
	 */
	public function __construct(
		public FeedFormat $format,
		public string $title,
		public string $link,
		public string $feedUrl,
		public DateTimeImmutable $updated = new DateTimeImmutable('@0'),
		public string $description = '',
		public string $language = 'en-US',
		public array $items = []
	) {}

	/**
	 * Returns the JSON Feed 1.1 document.
	 *
	 * @return array<string, mixed>
	 */
	public function jsonFeed(): array
	{
		$items = [];

		foreach ($this->items as $item) {
			$items[] = array_filter([
				'id'             => $item->url,
				'url'            => $item->url,
				'title'          => $item->title,
				'content_html'   => $item->content !== '' ? $item->content : $item->summary,
				'summary'        => trim(strip_tags($item->summary)),
				'date_published' => $item->published->format(DateTimeInterface::RFC3339),
				'date_modified'  => $item->updated->format(DateTimeInterface::RFC3339),
				'authors'        => array_map(static fn (string $name): array => ['name' => $name], $item->authors),
				'tags'           => $item->categories
			], static fn (mixed $value): bool => $value !== '' && $value !== []);
		}

		return array_filter([
			'version'       => 'https://jsonfeed.org/version/1.1',
			'title'         => $this->title,
			'home_page_url' => $this->link,
			'feed_url'      => $this->feedUrl,
			'description'   => $this->description,
			'language'      => $this->language,
			'items'         => $items
		], static fn (mixed $value): bool => $value !== '');
	}
}
