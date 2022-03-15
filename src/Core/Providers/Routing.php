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

use Blush\Core\ServiceProvider;
use Blush\Routing\{Component, Routes, Router};

class Routing extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register(): void
	{
		// Bind routes.
                $this->app->singleton( Routes::class );

		// Binds the router.
                $this->app->singleton( Router::class, function( $app ) {
			return new Router( $app->make( Routes::class ) );
		} );

		// Binds the routing component.
		$this->app->singleton( Component::class, function( $app ) {
			return new Component(
				$app->make( Routes::class   ),
				$app->make( 'content.types' )
			);
		} );

		// Add aliases.
		$this->app->alias( Routes::class, 'routing.routes' );
		$this->app->alias( Router::class, 'routing.router' );
		$this->app->alias( Router::class, 'router'         );
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
