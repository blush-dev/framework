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
use Blush\Cache\Driver\{File, HtmlFile, JsonFile, CollectionFile};

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

			// Merge default and user-configured drivers.
			$drivers = array_merge( [
				'file'            => File::class,
				'file.html'       => HtmlFile::class,
				'file.json'       => JsonFile::class,
				'file.collection' => CollectionFile::class
			], $app->make( 'config' )->get( 'cache.drivers' ) );

			// Merge default and user-configured stores.
			$stores = array_merge( [
				'content' => [
					'driver' => 'file.json',
					'path'   => cache_path( 'content' )
				],
				'global'  => [
					'driver' => 'file.html',
					'path'   => cache_path( 'global' )
				]
			], $app->make( 'config' )->get( 'cache.stores'  ) );

			// Creates the cache component.
			return new Component(
				$app->make( Registry::class ),
				$drivers,
				$stores
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
