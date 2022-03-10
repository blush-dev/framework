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
use Blush\Proxies\App;
use Blush\Controllers;
use Blush\Tools\Collection;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router implements Bootable {

	/**
	 * Routes collection.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Collection
	 */
	protected $routes;

	/**
	 * HTTP Request.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Request
	 */
	protected $request;

	/**
	 * Sets up the object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  Routes  $routes
	 * @return void
	 */
	public function __construct( Routes $routes ) {
		$this->request = Request::createFromGlobals();
                $this->routes  = $routes;
	}

	/**
	 * Bootstraps the component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  Routes  $routes
	 * @return void
	 */
	public function boot() {
		$types = App::resolve( 'content/types' );
		$types = array_reverse( $types->sortByPath() );

		// Loop through the content types and add their routes.
		foreach ( (array) $types as $type ) {
			foreach ( $type->routes() as $uri => $args ) {
				$this->routes->add( $uri, $args );
			}
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
	 * @since  1.0.0
	 * @access public
	 * @return Request
	 */
	public function request() {
		return $this->request;
	}

	/**
	 * Returns the request path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function path() {
		return $this->request->getPathInfo();
	}

	/**
	 * Returns a cached HTTP Response if global caching is enabled.  If not,
	 * returns a new HTTP Response.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return Response
	 */
	public function response() {
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
	 * @since  1.0.0
	 * @access public
	 * @return Response
	 */
	private function getResponse() {
	        $path   = $this->path();
	        $routes = $this->routes->all();

	        // Trim slashes unless homepage.
	        if ( '/' !== $path ) {
	                $path = Str::slashTrim( $path );
	        }

	        // Check for routes that are an exact match for the request.
	        if ( isset( $routes[ $path ] ) ) {
	                $callback = $routes[ $path ]->controller();

	                if ( is_string( $callback ) ) {
	                        $callback = new $callback;
	                }

	                return $callback( [ 'path' => $path ] );
	        }

	        // Loops through all routes and try to match them based on the
	        // variables contained in the route URI.
	        foreach ( $routes as $route ) {

	                // Skip homepage route here.
	                if ( '/' === $route->uri() ) {
	                        continue;
	                }

	                if ( @preg_match( $route->regex(), $path, $matches ) ) {

	                        // Removes the full match from the array.
	                        // Results match the route regex vars.
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

	                        // Gets the controller for the route.
	                        $callback = $route->controller();

	                        // If it is a string, assume it is a classname
	                        // and create a new instance.
	                        if ( is_string( $callback ) ) {
	                                $callback = new $callback;
	                        }

	                        // Call class as a function, which triggers the
	                        // __invoke() method.
	                        return $callback( $params );
	                }
	        }

	        // If nothing is found, send response.
	        // @todo - Send 404.
	        return new Response( '' );
	}
}
