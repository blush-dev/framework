<?php
/**
 * Cache service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Core\ServiceProvider;
use Blush\Cache\{Component, Registry};

class Cache extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register(): void
	{
		// Bind cache registry.
                $this->app->singleton( Registry::class );

		// Binds the cache component.
		$this->app->singleton( Component::class, function( $app ) {
			return new Component(
				$app->make( Registry::class )
			);
		} );

		// Add aliases.
		$this->app->alias( Registry::class, 'cache.registry' );
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
