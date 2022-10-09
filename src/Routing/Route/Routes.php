<?php
/**
 * Routes registry.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing\Route;

use Blush\App;
use Blush\Contracts\Routing\{RoutingRoute, RoutingRoutes};
use Blush\Tools\Collection;

class Routes extends Collection implements RoutingRoutes
{
	/**
	 * Holds an array of the route objects by name.
	 *
	 * @since  1.0.0
	 */
	protected array $named_routes = [];

	/**
	 * Add a route.
	 *
	 * @since  1.0.0
	 */
	public function add( mixed $uri, mixed $options = [] ): void
	{
		parent::add( $uri, App::make( 'routing.route', [
			'uri'     => $uri,
			'options' => $options
		] ) );

		$this->get( $uri )->make();
	}

	/**
	 * Returns route by name.
	 *
	 * @since  1.0.0
	 */
	public function getNamedRoute( string $name ): ?RoutingRoute
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
		if ( $this->named_routes ) {
			return $this->named_routes;
		}

		foreach ( $this->all() as $route ) {
			$this->named_routes[ $route->getName() ] = $route;
		}

		return $this->named_routes;
	}
}
