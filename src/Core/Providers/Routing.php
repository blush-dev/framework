<?php
/**
 * Route service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Contracts\Routing\Route as RouteContract;
use Blush\Contracts\Routing\Router as RouterContract;
use Blush\Contracts\Routing\Routes as RoutesContract;
use Blush\Contracts\Routing\Url as UrlContract;

use Blush\Core\ServiceProvider;
use Blush\Routing\{Component, Router, Url};
use Blush\Routing\Routes\{Route, Registry};

class Routing extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register(): void
	{
		// Bind route.
		$this->app->bind( RouteContract::class, Route::class );

		// Bind route registry.
		$this->app->singleton( RoutesContract::class, Registry::class );

		// Binds the router.
                $this->app->singleton( RouterContract::class, function( $app ) {
			return new Router( $app->make( RoutesContract::class ) );
		} );

		// Binds the routing URL instance.
                $this->app->singleton( UrlContract::class, function( $app ) {
			return new Url( $app->make( RoutesContract::class ) );
		} );

		// Binds the routing component.
		$this->app->singleton( Component::class, function( $app ) {
			return new Component(
				$app->make( RoutesContract::class ),
				$app->make( 'content.types' )
			);
		} );

		// Add aliases.
		$this->app->alias( RouteContract::class,  'routing.route'  );
		$this->app->alias( RoutesContract::class, 'routing.routes' );
		$this->app->alias( RouterContract::class, 'routing.router' );
		$this->app->alias( UrlContract::class,    'routing.url'    );

		// @deprecated 1.0.0
		$this->app->alias( RouterContract::class, 'router' );
        }

	/**
	 * Bootstrap bindings.
	 *
	 * @since 1.0.0
	 */
        public function boot(): void
	{
                $this->app->make( Component::class )->boot();
        }
}
