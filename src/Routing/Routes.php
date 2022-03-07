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

use Blush\Proxies\App;
use Blush\Tools\Collection;

class Routes {
	protected $routes;
	protected $routers = [];
	protected $route_regexes = [];
	protected $route_vars = [];

	public function __construct() {
		$this->routes = new Collection();
	}

	public function routes() {
		return $this->routes;
	}

	public function routers() {
		return $this->routers;
	}

	public function regexes() {
		return $this->route_regexes;
	}

	public function vars() {
		return $this->route_vars;
	}

	public function all() {
		return $this->routes;
	}

	public function get( $route, $callback, $position = 'bottom' ) {

		$router = [
			'callback' => $callback,
			'position' => $position
		];

		$this->routes[ $route ] = $callback;

		$regex = preg_replace( '/\{.*?\}/', '(.+)', $route );
		$regex = ltrim( $regex, '/' );
		$regex = str_replace( '/', '\/', $regex );
		$regex = "#{$regex}#i";

		$router['regex'] = $regex;

		preg_match_all( '/\{(.*?)\}/', $route, $matches );

		$route_vars = [];

		if ( $matches && isset( $matches[1] ) ) {

			foreach ( $matches[1] as $match ) {
				$route_vars[] = $match;
			}
		}

		$router['vars'] = $route_vars;

		if ( 'top' === $position ) {
			$this->routers = [ $route => $router ] + $this->routers;
		} else {
			$this->routers[ $route ] = $router;
		}
	}
}
