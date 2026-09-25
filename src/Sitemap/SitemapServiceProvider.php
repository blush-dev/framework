<?php

/**
 * Sitemap service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds sitemaps and `robots.txt`.
 */
final class SitemapServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		SitemapBuilder::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		SitemapController::class,
		RobotsController::class,
		SitemapRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [SitemapRoutes::class]
	];
}
