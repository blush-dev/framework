<?php
/**
 * Cache component.
 *
 * Bootstraps the cache component, acting as a bridge to the cache registry.
 * On booting, it sets up the default and user-configured drivers and stores.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

use Blush\Contracts\Bootable;
use Blush\Cache\Driver\{File, HtmlFile, JsonFile, CollectionFile};

class Component implements Bootable
{
	/**
	 * Cache registry.
	 *
	 * @since  1.0.0
	 */
	protected Registry $registry;

	/**
	 * Sets up object state.
	 *
	 * @since  1.0.0
	 */
	public function __construct( Registry $registry )
	{
		$this->registry = $registry;
	}

	/**
	 * Bootstraps the component, setting up cache drivers and stores.
	 *
	 * @since  1.0.0
	 */
	public function boot(): void
	{
		// Get user-configured drivers and stores.
		$drivers = config( 'cache.drivers' );
		$stores  = config( 'cache.stores'  );

		// Merge default and user-configured drivers.
		$drivers = array_merge( [
			'file'            => File::class,
			'file.html'       => HtmlFile::class,
			'file.json'       => JsonFile::class,
			'file.collection' => CollectionFile::class
		], $drivers ? (array) $drivers : [] );

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
		], $stores ? (array) $stores : [] );

		// Add drivers to the cache registry.
		foreach ( $drivers as $name => $driver ) {
			$this->registry->addDriver( $name, $driver );
		}

		// Add stores to the cache registry.
		foreach ( $stores as $name => $options ) {
			$this->registry->addStore( $name, $options );
		}
	}
}
