<?php

/**
 * Feed item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use DateTimeImmutable;
use Blush\Content\Entry\Entry;

/**
 * One entry in a feed, with everything the feed templates print: absolute
 * URLs, dates, the content (the rendered body, or `''` when the feed
 * carries excerpts only), the excerpt, author names, and category names.
 */
final readonly class FeedItem
{
	/**
	 * @param list<string> $authors
	 * @param list<string> $categories
	 */
	public function __construct(
		public Entry $entry,
		public string $title,
		public string $url,
		public DateTimeImmutable $published,
		public DateTimeImmutable $updated,
		public string $content = '',
		public string $summary = '',
		public array $authors = [],
		public array $categories = []
	) {}
}
