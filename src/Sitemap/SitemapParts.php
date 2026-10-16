<?php

/**
 * Sitemap parts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Blush\Content\Query\Query;

/**
 * A type's sitemap in three parts (D-912): the URLs before its entries
 * (the homepage, its collection page, or every profile or term archive),
 * the query for its entries with how many it matches, and the URLs after
 * (relation archives). The entries are counted, not built, so a page
 * builds only its own.
 */
final readonly class SitemapParts
{
	/**
	 * @param list<SitemapUrl> $before
	 * @param list<SitemapUrl> $after
	 */
	public function __construct(
		public array $before,
		public ?Query $query = null,
		public int $entries = 0,
		public array $after = []
	) {}

	/**
	 * Returns how many URLs the sitemap holds, at most: entries without
	 * URLs are counted too.
	 */
	public function total(): int
	{
		return count($this->before) + $this->entries + count($this->after);
	}
}
