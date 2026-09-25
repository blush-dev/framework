<?php

/**
 * Route priority.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * Where a route comes from, in precedence order (highest first). When two
 * routes claim the same method and path, the one from the higher-priority
 * source wins and the other is reported as shadowed. Static paths always
 * match before patterns, whatever their source; among patterns, priority
 * decides, then registration order.
 */
enum RoutePriority: int
{
	/**
	 * Framework routes that content must never hijack: feeds, sitemaps,
	 * `robots.txt`, the publish webhook, and the admin.
	 */
	case System = 0;

	/**
	 * Routes generated from content types (M4).
	 */
	case Content = 1;

	/**
	 * Controllers declared with routing attributes.
	 */
	case Controllers = 2;

	/**
	 * Routes listed in `config/routes.php`.
	 */
	case Config = 3;

	/**
	 * Catch-alls and placeholders that anything else may override, such as
	 * the page catch-all and the welcome page.
	 */
	case Fallback = 4;

	/**
	 * Returns a short label for listings.
	 */
	public function label(): string
	{
		return strtolower($this->name);
	}
}
