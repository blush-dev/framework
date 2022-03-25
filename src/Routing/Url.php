<?php
/**
 * URL class.
 *
 * The primary use case of this class is to provide a bridge between routes and
 * their URIs and other components of the framework. It provides a convenient
 * `route()` method for accessing a named route's URL.  There is also a general-
 * purpose `to()` method for appending any arbitrary path to the app URL.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

use Blush\App;
use Blush\Contracts\Makeable;

class Url implements Makeable
{
	/**
	 * Collection of Routes.
	 *
	 * @since 1.0.0
	 */
	protected Routes $routes;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( Routes $routes )
	{
		$this->routes = $routes;
	}

	/**
	 * Return the object instance.
	 *
	 * @since 1.0.0
	 */
	public function make(): self
	{
		return $this;
	}

	/**
	 * Return the app URL and append an optional path.
	 *
	 * @since 1.0.0
	 */
	public function to( string $append = '' ): string
	{
		return App::url( '', $append );
	}

	/**
	 * Returns a route's URL. For variable routes, the params should be
	 * passed in via the `$params` array in key/value pairs like:
	 * `[ 'number' => 10 ]`.
	 *
	 * @since 1.0.0
	 */
	public function route( string $name, array $params = [] ): string
	{
		$route = $this->routes->getNamedRoute( $name );

		// If route not found, return app URL.
		if ( ! $route ) {
			return $this->to();
		}

		$url_path = $route->uri();

		// Replace parameters with values.
		foreach ( $params as $param => $value ) {
			$url_path = str_replace(
				sprintf( '{%s}', $param ),
				$value,
				$url_path
			);
		}

		return $this->to( $url_path );
	}

	/**
	 * When attempting to use the object as a string, return the result
	 * of the `to()` method.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->to();
	}
}
