<?php
/**
 * App service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Core\ServiceProvider;
use Blush\Template\Engine;
use Blush\Template\View;
use Blush\Tools\Collection;

class App extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {

                // Get the app config collection.
                $app_config = $this->app->config->get( 'app' );

                // Sets the default timezone.
                date_default_timezone_set( $app_config['timezone'] ?? 'America/Chicago' );

		// Add cache.
                $this->app->instance( 'cache', new Collection() );

		// Add template engine.
                $this->app->bind( View::class );
		$this->app->singleton( Engine::class );
        }
}
