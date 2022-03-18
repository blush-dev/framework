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

use Blush\Tools\Str;

class Registry
{
	/**
	 * Stores registered cache drivers.
	 *
	 * @since 1.0.0
	 */
	protected array $drivers = [];

	/**
	 * Stores registered cache drivers.
	 *
	 * @since 1.0.0
	 */
	protected array $stores = [];

	/**
	 * Get data from a store's cache.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function get( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->get( $key );
	}

	/**
	 * Write data to a store's cache. Overwrites existing data.
	 *
	 * @since  1.0.0
	 */
	public function put( string $name, $data, $expire = 0 ): bool
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->put( $key, $data, $expire );
	}

	/**
	 * Add data to a store's cache if it doesn't exist.
	 *
	 * @since  1.0.0
	 */
	public function add( string $name, $data, $expire = 0 )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->add( $key, $data, $expire );
	}

	/**
	 * Write data to a store's cache with no expiration.
	 *
	 * @since  1.0.0
	 */
	public function forever( string $name, $data )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->forever( $key, $data );
	}

	public function remember( string $name, $expire, Closure $callback )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->remember( $key );

		// code goes in driver class.
		$data = $this->store( $store )->get( $key );

		if ( ! $data ) {
			$data = $concrete();
			if ( $data ) {
				$this->store( $store )->put( $key, $data, $expire );
			}
		}

		return $data;
	}

	public function rememberForever( string $name, Closure $callback )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->rememberForever( $key, $callback );
	}

	public function has( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->has( $key );
	}

	public function delete( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		return $this->store( $store )->delete( $key );
	}

	public function forget( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->forget( $key );
	}

	public function flush( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$this->store( $store )->flush( $key );
	}

	// Get and delete. "pull" from cache.
	public function pull( string $name )
	{
		[ 'store' => $store, 'key' => $key ] = $this->parseDotName( $name );

		$data = $this->store( $store )->get();

		$this->store( $store )->delete( $key );

		return $data;
	}

	public function store( $name )
	{
		if ( isset( $this->stores[ $name ] ) ) {
			return $this->stores[ $name ]->make();
		}

		return false;
	}

	public function getStores(): array
	{
		$stores = [];

		foreach ( $this->stores as $store => $options ) {
			$stores[ $store ] = $this->store( $store );
		}

		return $stores;
	}

	public function driver( $name )
	{
		return $this->drivers[ $name ] ?? false;
	}

	public function addDriver( string $name, string $driver )
	{
		$this->drivers[ $name ] = $driver;
	}

	public function removeDriver( string $name )
	{
		if ( isset( $this->drivers[ $name ] ) ) {
			unset( $this->drivers[ $name ] );
		}
	}

	public function addStore( string $name, array $options = [] )
	{
		$options = array_merge( [
			'driver' => 'file',
			'path'   => cache_path( $name )
		], $options );

		if ( $this->driverExists( $options['driver'] ) ) {
			$driver = $this->driver( $options['driver'] );

			$this->stores[ $name ] = new $driver( $name, $options );
		}
	}

	public function driverExists( $name ): bool
	{
		return isset( $this->drivers[ $name ] );
	}

	public function removeStore( string $store )
	{
		if ( isset( $this->stores[ $store ] ) ) {
			unset( $this->stores[ $store ] );
		}
	}

	public function storeExists( string $name ): bool
	{
		return isset( $this->stores[ $name ] );
	}

	private function parseDotName( string $name ) {
		$store = Str::beforeFirst( $name, '.' );
		$key   = Str::afterFirst( $name, '.' );

		if ( $store === $key ) {
			return false; // @todo dump error message.
		}

		if ( ! $this->storeExists( $store ) ) {
			return false; // @todo dump error message.
		}

		return [ 'store' => $store, 'key' => $key ];
	}
}
