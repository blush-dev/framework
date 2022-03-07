<?php
/**
 * Content service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Core\ServiceProvider;
use Blush\Content\Types\Component;
use Blush\Content\Types\Types;

class Content extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {
                $this->app->singleton( 'content/types', Types::class );

                $this->app->singleton( Component::class, function() {
                        return new Component(
                                $this->app->resolve( 'content/types' ),
                                $this->app->config->get( 'content' )
                        );
                } );
        }

	/**
	 * Bootstrap bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function boot() {
                $this->app->resolve( Component::class )->boot();
        }
}
