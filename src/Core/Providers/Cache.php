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

use Blush\Contracts\Cache\Registry as CacheRegistry;

use Blush\Core\ServiceProvider;
use Blush\Cache\{Component, Registry};
use Blush\Cache\Drivers\{File, JsonFile};

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
                $this->app->singleton( CacheRegistry::class, Registry::class );

		// Binds the cache component.
		$this->app->singleton( Component::class, function( $app ) {

			// Merge default and user-configured drivers.
			$drivers = array_merge( [
				'file'       => File::class,
				'file.cache' => File::class,
				'file.json'  => JsonFile::class
			], $app->make( 'config' )->get( 'cache.drivers' ) );

			// Merge default and user-configured stores.
			$stores = array_merge( [
				'content' => [
					'driver' => 'file.json',
					'path'   => $app->cachePath( 'content' )
				],
				'global'  => [
					'driver' => 'file.cache',
					'path'   => $app->cachePath( 'global' )
				]
			], $app->make( 'config' )->get( 'cache.stores'  ) );

			// Creates the cache component.
			return new Component(
				$app->make( CacheRegistry::class ),
				$drivers,
				$stores
			);
		} );

		// Add aliases.
		$this->app->alias( CacheRegistry::class, 'cache.registry' );
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
