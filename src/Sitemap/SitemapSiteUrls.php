<?php

/**
 * Sitemap site URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Override;
use Blush\Routing\SiteUrl;
use Blush\Routing\UrlSource;

/**
 * Lists `robots.txt` and, when sitemaps are on, the sitemap index (at
 * `/sitemap` and `/sitemap.xml`) and each sitemap it lists (D-136,
 * D-476).
 */
final readonly class SitemapSiteUrls implements UrlSource
{
	public function __construct(
		private SitemapBuilder $builder,
		private SitemapConfig $config
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		yield new SiteUrl('/robots.txt');

		if (! $this->config->enabled) {
			return;
		}

		yield new SiteUrl('/sitemap');
		yield new SiteUrl('/sitemap.xml');

		foreach ($this->builder->index() as [$path]) {
			yield new SiteUrl($path);
		}
	}
}
