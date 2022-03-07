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
use Blush\Controllers;
use Blush\Routing\Routes;
use Blush\Routing\Router;

class Route extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {
                $this->app->instance( 'routes', new Routes() );
                $this->app->instance( 'router', new Router( $this->app->routes ) );
        }

	/**
	 * Bootstrap bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function boot() {
		// Todo build `$route->where()` checks that match content types.
		// This will be more accurate and efficient than all the checks
		// in the current controllers.
		// $this->app->routes->get( '{content_type}',               Controllers\ContentType::class );
		// $this->app->routes->get( '{content_type}/page/{number}', Controllers\ContentType::class );
		// $this->app->routes->get( '{content_type}/{name}',        Controllers\ContentType::class );

		$this->app->routes->get( '/',                    Controllers\Home::class    );
                $this->app->routes->get( 'cache/purge/{key}',    Controllers\Cache::class   );
                $this->app->routes->get( '{name}/page/{number}', Controllers\Content::class );
                $this->app->routes->get( '{name}',               Controllers\Content::class );
        }
}
