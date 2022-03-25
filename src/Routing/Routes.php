<?php
/**
 * Routes collection.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

use Blush\App;
use Blush\Tools\Collection;

class Routes extends Collection
{
	/**
	 * Add a route.
	 *
	 * @since  1.0.0
	 * @param  string  $uri
	 * @param  array   $args
	 */
	public function add( $uri, $args = [] ): void
	{
		parent::add( $uri, new Route( $uri, $args ) );

		$this->get( $uri )->make();
	}

	/**
	 * Returns route by name.
	 *
	 * @since  1.0.0
	 */
	public function getNamedRoute( string $name ): ?Route
	{
		$routes = $this->getRoutesByName();

		return $routes[ $name ] ?? null;
	}

	/**
	 * Returns an array of all routes with their names as the keys and the
	 * Route objects as the values.
	 *
	 * @since  1.0.0
	 */
	public function getRoutesByName(): array
	{
		$routes = [];

		foreach ( $this->all() as $route ) {
			$routes[ $route->getName() ] = $route;
		}

		return $routes;
	}
}
