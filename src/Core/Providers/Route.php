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
		$this->app->resolve( 'router' )->boot();
        }
}
