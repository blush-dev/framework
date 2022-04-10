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
use Blush\Contracts\Routing\Routes;
use Blush\Contracts\Routing\Url as UrlContract;

class Url implements Makeable, UrlContract
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected Routes $routes ) {}

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
		return App::url( append: $append );
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
		$path = false;

		if ( isset( $this->routes[ $name ] ) ) {
			$path = $this->routes[ $name ]->uri();
		} elseif ( $route = $this->routes->getNamedRoute( $name ) ) {
			$path = $route->uri();
		}

		return $path ? $this->to(
			$this->parseParams( $path, $params )
		) : '';
	}

	/**
	 * Accepts a path or URL string with possible `{param}` values in it.
	 * Replaces the `{param}` strings with values from the `$params` array.
	 *
	 * @since 1.0.0
	 */
	public function parseParams( string $path, array $params = [] ): string
	{
		// Replace parameters with values.
		foreach ( $params as $param => $value ) {
			$path = str_replace(
				sprintf( '{%s}', $param ),
				$value,
				$path
			);
		}

		return $path;
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
