<?php

/**
 * Site URL source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * Lists URL paths the site serves, for anything that needs to visit
 * every page, such as a static exporter, a cache warmer, or a link
 * checker in a plugin (D-476; first written for static export, D-136).
 * The framework's sources cover content (entries, collections, terms,
 * date archives, and people), feeds, sitemaps, `llms.txt` and Markdown
 * pages, and redirects; an extension whose routes serve pages tags its
 * own source with `UrlSource::TAG` in a provider's `TAGS`. `SiteUrls`
 * gathers them all.
 */
interface UrlSource
{
	/**
	 * The container tag for site URL sources.
	 */
	public const string TAG = 'site.urls';

	/**
	 * Returns the site's URLs. Paths may repeat; `SiteUrls` keeps the
	 * first to list a path, which decides whether it's paged.
	 *
	 * @return iterable<SiteUrl>
	 */
	public function urls(): iterable;
}
