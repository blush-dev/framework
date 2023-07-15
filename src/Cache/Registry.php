<?php
/**
 * Cache registry.
 *
 * Houses access to the cache system stores and provides wrapper methods for
 * quickly accessing cache data.
 *
 * For the store wrapper methods, access them via dot notation, such as
 * `Blush\Cache::get( 'store.key' )`.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

use Blush\Contracts\Cache\Driver;
use Blush\Contracts\Cache\Registry as CacheRegistry;

use Closure;
use Blush\Core\Proxies\Message;
use Blush\Tools\Str;

class Registry implements CacheRegistry
{
	/**
	 * Stores registered cache drivers.
	 *
	 * @since 1.0.0
	 */
	protected array $drivers = [];

	/**
	 * Stores registered cache stores.
	 *
	 * @since 1.0.0
	 */
	protected array $stores = [];

	/**
	 * Returns a store driver object or `false`.
	 *
	 * @since  1.0.0
	 */
	public function store( string $store ): Driver|false
	{
		if ( isset( $this->stores[ $store ] ) ) {
			return $this->stores[ $store ];
		}

		return false;
	}

	/**
	 * Returns all stores.
	 *
	 * @since  1.0.0
	 */
	public function getStores(): array
	{
		return $this->stores;
	}

	/**
	 * Adds a store. A driver and path are the minimum requirements for a
	 * store, so those are added as defaults. However, individual driver
	 * implementations may have additional `$options` requirements.
	 *
	 * @since  1.0.0
	 */
	public function addStore( string $name, array $options = [] ): void
	{
		$options = array_merge( [ 'driver' => 'file' ], $options );

		// If this is a file-based driver, make sure it has a full path
		// for the cache directory. By default, we'll use the store name.
		if ( Str::startsWith( $options['driver'], 'file' ) && ! isset( $options['path'] ) ) {
			$options['path'] = cache_path( $name );
		}

		// Add a new store if the driver is registered.
		if ( $this->driverExists( $options['driver'] ) ) {
			$driver = $this->driver( $options['driver'] );

			// Create a new store via its driver.
			$store = new $driver( $name, $options );

			// Add the store object to the registry and make it.
			$this->stores[ $name ] = $store->make();
		}
	}

	/**
	 * Removes a store.
	 *
	 * @since  1.0.0
	 */
	public function removeStore( string $store ): void
	{
		if ( isset( $this->stores[ $store ] ) ) {
			unset( $this->stores[ $store ] );
		}
	}

	/**
	 * Checks if a store exists.
	 *
	 * @since  1.0.0
	 */
	public function storeExists( string $store ): bool
	{
		return isset( $this->stores[ $store ] );
	}

	/**
	 * Returns a driver.
	 *
	 * @since  1.0.0
	 */
	public function driver( string $name ): string|false
	{
		return $this->drivers[ $name ] ?? false;
	}

	/**
	 * Checks if a driver exists.
	 *
	 * @since  1.0.0
	 */
	public function driverExists( string $name ): bool
	{
		return isset( $this->drivers[ $name ] );
	}

	/**
	 * Adds a driver.
	 *
	 * @since  1.0.0
	 */
	public function addDriver( string $name, string $driver ): void
	{
		$this->drivers[ $name ] = $driver;
	}

	/**
	 * Removes a driver.
	 *
	 * @since  1.0.0
	 */
	public function removeDriver( string $name ): void
	{
		if ( isset( $this->drivers[ $name ] ) ) {
			unset( $this->drivers[ $name ] );
		}
	}

	/**
	 * Check if the store has data via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function has( string $name ): bool
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->has( $key );
	}

	/**
	 * Returns data from a store via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function get( string $name ): mixed
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->get( $key );
	}

	/**
	 * Writes new data or replaces existing data via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function put( string $name, mixed $data, int $seconds = 0 ): bool
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->put( $key, $data, $seconds );
	}

	/**
	 * Writes new data if it doesn't exist via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function add( string $name, $data, int $seconds = 0 ): void
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->add( $key, $data, $seconds );
	}

	/**
	 * Deletes data if it exists via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function forget( string $name ): void
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->forget( $key );
	}

	/**
	 * Writes new data if it doesn't exist via `store.key`. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function forever( string $name, $data ): void
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->forever( $key, $data );
	}

	/**
	 * Gets and returns data via `store.key`. If it doesn't exist, callback
	 * is executed to pass in custom data and write it.
	 *
	 * @since  1.0.0
	 */
	public function remember( string $name, int $seconds, Closure $callback ): mixed
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->remember( $key, $seconds, $callback );
	}

	/**
	 * Gets and returns data via `store.key`. If it doesn't exist, callback
	 * is executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function rememberForever( string $name, Closure $callback ): mixed
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->rememberForever( $key, $callback );
	}

	/**
	 * Gets and returns data via `store.key`. Deletes previous data.
	 *
	 * @since  1.0.0
	 */
	public function pull( string $name ): mixed
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->pull( $key );
	}

	/**
	 * Returns the timestamp for when a dataset was created via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function created( string $name ): ?int
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->created( $key );
	}

	/**
	 * Returns the timestamp for when a dataset expires via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function expires( string $name ): ?int
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->expires( $key );
	}

	/**
	 * Determines if a dataset has expired via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function expired( string $name ): bool
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->expires( $key );
	}

	/**
	 * Deletes all cached data from a store.
	 *
	 * @since  1.0.0
	 */
	public function flush( string $store ): void
	{
		$this->store( $store )->flush();
	}

	/**
	 * Flushes the cached data from all stores.
	 *
	 * @since  1.0.0
	 */
	public function purge(): void
	{
		foreach ( $this->getStores() as $store ) {
			$store->flush();
		}
	}

	/**
	 * Helper function for parsing a store and key name via dot notation.
	 *
	 * @since  1.0.0
	 */
	private function parseDotName( string $name ): array
	{
		$store = Str::beforeFirst( $name, '.' );
		$key   = Str::afterFirst( $name, '.' );

		if ( $store === $key ) {
			Message::make(
				'Cached data must be accessed via dot notation (.e.g, <code>store.key</code>).'
			)->dd();
		}

		if ( ! $this->storeExists( $store ) ) {
			Message::make(
				"Cache store <code>{$store}</code> does not exist."
			)->dd();
		}

		return [ 'store' => $store, 'key' => $key ];
	}
}
