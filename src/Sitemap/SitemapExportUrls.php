<?php

/**
 * Sitemap export URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Override;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists `robots.txt` and, when sitemaps are on, the sitemap index (at
 * `/sitemap` and `/sitemap.xml`) and each sitemap it lists, for static
 * export (D-136).
 */
final readonly class SitemapExportUrls implements UrlSource
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
		yield new ExportUrl('/robots.txt');

		if (! $this->config->enabled) {
			return;
		}

		yield new ExportUrl('/sitemap');
		yield new ExportUrl('/sitemap.xml');

		foreach ($this->builder->index() as [$path]) {
			yield new ExportUrl($path);
		}
	}
}
