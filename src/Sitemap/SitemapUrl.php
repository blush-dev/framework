<?php

/**
 * Sitemap URL.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use DateTimeImmutable;

/**
 * One `<url>` (or, in the index, one `<sitemap>`): an absolute location
 * and when it last changed.
 */
final readonly class SitemapUrl
{
	public function __construct(
		public string $loc,
		public ?DateTimeImmutable $lastmod = null
	) {}
}
