<?php

namespace Blush\Routing;

use Blush\Proxies\App;
use Blush\Tools\Collection;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router {

	protected $routes;
	protected $request;

	public function __construct( $routes ) {
		$this->request = Request::createFromGlobals();
                $this->routes  = $routes;
	}

	public function request() {
		return $this->request;
	}

	public function path() {
		return $this->request->getPathInfo();
	}

	public function response() {
		$config = config( 'cache' );

		// Just return the response if global caching is disabled.
		if ( ! $config['global'] ) {
			return $this->getResponse();
		}

		$exclude = $config['global_exclude'] ?? [];
		$path    = trim( $this->path(), '/' );

		// Don't cache excluded pages. Just return response.
		foreach ( (array) $exclude as $_path ) {
			if ( Str::startsWith( $path, 'cache' ) ) {
				return $this->getResponse();
			}
		}

		if ( ! $path ) {
			$path = 'index';
		}

		$cache_key = "global/{$path}";
		$content   = cache_get_make( $cache_key, 'html' );
		$response  = false;

		if ( ! $content ) {
			$response = $this->getResponse();
			$content  = $this->getResponse()->getContent();
			cache_set( $cache_key, $content, 'html' );
		}

		if ( ! $response ) {
			$response = new Response();
			$response->setContent( $content );
		}

		return $response;
	}

	private function getResponse() {
		$request = $this->path();
		$routes  = $this->routes->routes();

		// Trim slashes unless homepage.
		if ( '/' !== $request ) {
			$request = trim( $request, '/' );
		}

		// Check for routes that are an exact match for the request.
		if ( isset( $routes[ $request ] ) ) {
			$callback = $routes[ $request ];

			if ( is_string( $callback ) ) {
				$callback = new $callback;
			}

			return $callback();
		}

		$request_parts = explode( '/', $request );

		foreach ( $this->routes->routers() as $route => $args ) {

			// Skip homepage route here.
			if ( '/' === $route ) {
				continue;
			}

			if ( @preg_match( $args['regex'], $request, $matches ) ) {

				array_shift( $matches );

				$params = array_combine( $args['vars'], $matches );

				$params['path'] = $request;

				$callback = $args['callback'];

				if ( is_string( $callback ) ) {
					$callback = new $callback;
				}

				return $callback( $params );
			}
		}

		return new Response( '' );
	}
}
