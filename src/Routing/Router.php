<?php
/**
 * Router class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

use Blush\Contracts\Routing\Routes;
use Blush\Contracts\Routing\Router as RouterContract;

use Blush\{Cache, Config};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class Router implements RouterContract
{
	/**
	 * HTTP Request.
	 *
	 * @since 1.0.0
	 */
	protected Request $request;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected Routes $routes )
	{
		$this->request = Request::createFromGlobals();
	}

	/**
	 * Returns the HTTP request.
	 *
	 * @since 1.0.0
	 */
	public function request(): Request
	{
		return $this->request;
	}

	/**
	 * Returns the request path.
	 *
	 * @since 1.0.0
	 */
	public function path(): string
	{
		return $this->request->getPathInfo();
	}

	/**
	 * Returns a cached HTTP Response if global caching is enabled.  If not,
	 * returns a new HTTP Response.
	 *
	 * @since 1.0.0
	 */
	public function response(): Response
	{
		// Just return the response if global caching is disabled.
		if ( ! Config::get( 'cache.global' ) ) {
			return $this->getResponse();
		}

		// Trim slashes from the path.
		$path = Str::trimSlashes( $this->path() );

		// Get excluded paths.
		$exclude = array_merge( [
			'purge/cache'
		], Config::get( 'cache.global_exclude' ) );

		// Don't cache excluded pages. Just return response.
		foreach ( (array) $exclude as $_path ) {
			if ( Str::startsWith( $path, $_path ) ) {
				return $this->getResponse();
			}
		}

		// If the path is empty, name it 'index'.
		if ( ! $path ) {
			$path = 'index';
		}

		$cache_key = str_replace( [ '/', '\\' ], '.', $path );
		$content = Cache::get( "global.{$cache_key}" );
		$response  = false;

		// If no cached content, get a new response and cache it.
		if ( ! $content ) {
			$response = $this->getResponse();
			$content  = $response->getContent();
			Cache::put(
				"global.{$cache_key}",
				$content,
				Config::get( 'cache.expires' )
			);
		}

		// If no response is set, add the cached content to new response.
		if ( ! $response ) {
			$response = new Response();
			$response->setContent( $content );
		}

		// Return HTTP response.
		return $response;
	}

	/**
	 * Returns an HTTP response.
	 *
	 * @since 1.0.0
	 */
	private function getResponse(): Response
	{
	        $path   = $this->path();
	        $routes = $this->routes->all();

	        // Trim slashes unless homepage.
	        if ( '/' !== $path ) {
	                $path = Str::trimSlashes( $path );
	        }

	        // Check for route that is an exact match for the request. This
		// will match route URIs that do not have variables, so we can
		// just return the matched route controller here.
	        if ( isset( $routes[ $path ] ) ) {
			return $routes[ $path ]->callback( [
				'path' => $path
			], $this->request() );
	        }

	        // Loops through all routes and try to match them based on the
	        // params contained in the route URI.
	        foreach ( $routes as $route ) {

	                // Skip routes without params.
	                if ( ! Str::contains( $route->uri(), '{' ) ) {
	                        continue;
	                }

			// Checks for matches against the route regex pattern,
			// e.g., `/path/{var_a}/example/{var_b}`.
			//
			// Individual `{var}` patterns are defined in the
			// `Route` class. If we have a full pattern match, we
			// pull out the `{var}` instances and set them as params
			// and pass them to the route's controller.
			if ( @preg_match( $route->pattern(), $path, $matches ) ) {

				// Removes the full match from the array, which
				// matches the entire URI path.  The leftover
				// matches are the parameter values.
	                        array_shift( $matches );

	                        // Combines the route vars as array keys and the
	                        // matches as the values.
	                        $params = array_combine(
	                                $route->parameters(),
	                                $matches
	                        );

	                        // If no path set, pass the request path.
	                        if ( ! isset( $params['path'] ) ) {
	                                $params['path'] = $path;
	                        }

				// Invoke the route callback.
				return $route->callback( $params, $this->request() );
	                }
	        }

	        // If nothing is found, send response.
	        // @todo - Send 404.
	        return new Response( '' );
	}
}
