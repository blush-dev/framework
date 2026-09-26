<?php

/**
 * Export URL source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

/**
 * Lists URL paths for static export to render (D-136). The framework's
 * sources cover content (entries, collections, terms, and date
 * archives), feeds, and sitemaps; an extension whose routes serve pages
 * tags its own source with `UrlSource::TAG` in a provider's `TAGS`.
 * Sources run in the export application, so they see the site as it's
 * exported.
 */
interface UrlSource
{
	/**
	 * The container tag for export URL sources.
	 */
	public const string TAG = 'export.urls';

	/**
	 * Returns the URLs to export. Paths may repeat; each is rendered once,
	 * and the first to list a path decides whether it's paged.
	 *
	 * @return iterable<ExportUrl>
	 */
	public function urls(): iterable;
}
