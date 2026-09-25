<?php

/**
 * Route source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * Supplies routes to the route table. The framework's sources cover
 * `config/routes.php`, attribute-routed controllers, and the fallbacks;
 * content types add theirs in M4. An extension adds routes by tagging its
 * own source with `RouteSource::TAG` in a provider's `TAGS`.
 *
 * Sources are only asked for routes when the table is compiled: on every
 * request in development, and by `cache:compile` elsewhere.
 */
interface RouteSource
{
	/**
	 * The container tag for route sources.
	 */
	public const string TAG = 'routing.sources';

	/**
	 * Where the routes rank when two claim the same method and path.
	 */
	public function priority(): RoutePriority;

	/**
	 * Returns the routes.
	 *
	 * @return iterable<Route>
	 */
	public function routes(): iterable;
}
