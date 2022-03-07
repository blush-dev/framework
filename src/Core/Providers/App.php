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
use Blush\Tools\Config;

class App extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {
                $this->app->instance( 'config', new Collection() );

                $files = glob( "{$this->app->path}/config/*.php" );

                foreach ( $files as $file ) {
                        $config = include $file;

                        if ( is_array( $config ) ) {
                                $this->app->config->add(
                                        basename( $file, '.php' ),
                                        new Config( $config )
                                );
                        }
                }

                // Get the app config collection.
                $app_config = $this->app->config->get( 'app' );

                // Sets the default timezone.
                date_default_timezone_set( $app_config['timezone'] ?? 'America/Chicago' );

                $this->app->instance( 'path/public',    "{$this->app->path}/public"       );
                $this->app->instance( 'path/resources', "{$this->app->path}/resources"    );
                $this->app->instance( 'path/user',      "{$this->app->path}/user"         );
                $this->app->instance( 'path/cache',     "{$this->app->path}/user/cache"   );
                $this->app->instance( 'path/content',   "{$this->app->path}/user/content" );
                $this->app->instance( 'path/media',     "{$this->app->path}/user/media"   );

                $uri = $this->app->config->get( 'app' )->get( 'uri' );

                $this->app->instance( 'uri', $app_config['uri'] );
                $this->app->instance( 'uri/relative', parse_url( $app_config['uri'], PHP_URL_PATH ) );

                $this->app->instance( 'cache', new Collection() );

                $this->app->bind( View::class );
		$this->app->singleton( Engine::class );
        }
}
