<?php

/**
 * Route cache refresher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Blush\Content\Events\ContentIndexed;
use Blush\Core\AppConfig;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RouteCache;

/**
 * Rewrites the compiled route table after the content index changes, so
 * `redirect_from` front matter takes effect in production without a
 * `cache:compile`. Only an existing cache is rewritten; development
 * compiles routes on every request anyway.
 */
final readonly class RefreshRouteCache
{
	public function __construct(
		private RouteCache $routes,
		private AppConfig $app
	) {}

	/**
	 * @throws InvalidRoute
	 */
	public function __invoke(ContentIndexed $event): void
	{
		if (! $this->app->environment->isDevelopment() && $event->report->hasChanges() && is_file($this->routes->path())) {
			$this->routes->write();
		}
	}
}
