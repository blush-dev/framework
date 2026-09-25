<?php

/**
 * Sitemap routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The system routes for crawlers: `robots` (`/robots.txt`), and, with
 * sitemaps on, `sitemap` (`/sitemap`, 1.x's path, also answered at
 * `/sitemap.xml`) and `sitemap.type` (`/sitemap/{type}`).
 */
final readonly class SitemapRoutes implements RouteSource
{
	public function __construct(private SitemapConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		$routes = [Route::get('/robots.txt', RobotsController::class)->named('robots')];

		if ($this->config->enabled) {
			$routes[] = Route::get('/sitemap', SitemapController::class)->named('sitemap');
			$routes[] = Route::get('/sitemap.xml', SitemapController::class)->named('sitemap.xml');
			$routes[] = Route::get('/sitemap/{type:[a-z0-9][a-z0-9_-]*}', SitemapController::class)->named('sitemap.type');
		}

		return $routes;
	}
}
