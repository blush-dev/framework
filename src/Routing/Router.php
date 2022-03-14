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

use Blush\Contracts\Bootable;
use Blush\App;
use Blush\Controllers;
use Blush\Tools\{Collection, Str};
use Symfony\Component\HttpFoundation\{Request, Response};

class Router implements Bootable
{
	/**
	 * Routes collection.
	 *
	 * @since 1.0.0
	 */
	protected Routes $routes;

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
	public function __construct( Routes $routes )
	{
		$this->request = Request::createFromGlobals();
                $this->routes  = $routes;
	}

	/**
	 * Bootstraps the component.
	 *
	 * @since 1.0.0
	 */
	public function boot() : void
	{
		$types = App::resolve( 'content/types' );

		// Get the homepage alias if it exists.
		$alias = \config( 'app', 'home_alias' );
		$alias = $alias && $types->has( $alias ) ? $types->get( $alias ) : false;

		// Sort the content types.
		$types = array_reverse( $types->sortByPath() );

		// Loop through the content types and add their routes.
		foreach ( (array) $types as $type ) {

			// Skip if the content type doesn't support routing.
			if ( ! $type->routing() ) {
				continue;
			}

			foreach ( $type->routes() as $uri => $args ) {
				$this->routes->add( $uri, $args );
			}
		}

		// Add paginated homepage route if we have content type alias.
		if ( $alias ) {
			$this->routes->add( '/page/{number}', [
				'controller' => Controllers\Home::class
			] );
		}

		// Add homepage route.
		$this->routes->add( '/', [
			'controller' => Controllers\Home::class
		] );

		// Add cache purge route.
		$this->routes->add( 'cache/purge/{key}', [
			'controller' => Controllers\Cache::class
		] );

		// Add catchall page route.
		$this->routes->add( '{*}', [
			'controller' => Controllers\SinglePage::class
		] );
	}

	/**
	 * Returns the HTTP request.
	 *
	 * @since 1.0.0
	 */
	public function request() : Request
	{
		return $this->request;
	}

	/**
	 * Returns the request path.
	 *
	 * @since 1.0.0
	 */
	public function path() : string
	{
		return $this->request->getPathInfo();
	}

	/**
	 * Returns a cached HTTP Response if global caching is enabled.  If not,
	 * returns a new HTTP Response.
	 *
	 * @since 1.0.0
	 */
	public function response() : Response
	{
		$config = config( 'cache' );

		// Just return the response if global caching is disabled.
		if ( ! $config['global'] ) {
			return $this->getResponse();
		}

		$exclude = $config['global_exclude'] ?? [];
		$path    = Str::slashTrim( $this->path() );

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

		$cache_key = "global/{$path}";
		$content   = cache_get_make( $cache_key, 'html' );
		$response  = false;

		// If no cached content, get a new response and cache it.
		if ( ! $content ) {
			$response = $this->getResponse();
			$content  = $this->getResponse()->getContent();
			cache_set( $cache_key, $content, 'html' );
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
	private function getResponse() : Response
	{
	        $path   = $this->path();
	        $routes = $this->routes->all();

	        // Trim slashes unless homepage.
	        if ( '/' !== $path ) {
	                $path = Str::slashTrim( $path );
	        }

	        // Check for route that is an exact match for the request. This
		// will match route URIs that do no have variables, so we can
		// just return the matched route controller here.
	        if ( isset( $routes[ $path ] ) ) {
			return $routes[ $path ]->callback( [
				'path' => $path
			] );
	        }

	        // Loops through all routes and try to match them based on the
	        // variables contained in the route URI.
	        foreach ( $routes as $route ) {

	                // Skip homepage route here.
			// @todo Also skip route URIs w/o variables.
	                if ( '/' === $route->uri() ) {
	                        continue;
	                }

			// Checks for matches against the route regex pattern,
			// e.g., `/path/{var_a}/example/{var_b}`.
			//
			// Individual `{var}` patterns are defined in the
			// `Route` class. If we have a full pattern match, we
			// pull out the `{var}` instances and set them as params
			// and pass them to the route's controller.
	                if ( @preg_match( $route->regex(), $path, $matches ) ) {

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
				return $route->callback( $params );
	                }
	        }

	        // If nothing is found, send response.
	        // @todo - Send 404.
	        return new Response( '' );
	}
}
