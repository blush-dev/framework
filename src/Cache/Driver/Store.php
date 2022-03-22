<?php
/**
 * Base store driver class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache\Driver;

use Closure;
use Blush\Contracts\Makeable;

abstract class Store
{
	/**
	 * Store name.
	 *
	 * @since  1.0.0
	 */
	protected string $name = '';

	/**
	 * Houses store data after it has been retreived from the cache.
	 *
	 * @since  1.0.0
	 */
	protected array $data = [];

	/**
	 * Sets up object state.
	 *
	 * @since  1.0.0
	 */
	public function __construct( string $name, array $options = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
		}

		$this->name = $name;

		if ( ! $this->path ) {
			$this->path = cache_path( $this->name );
		}
	}

	/**
	 * Returns the store name.
	 *
	 * @since  1.0.0
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Creates a store and returns store object for method chaining.
	 *
	 * @since  1.0.0
	 */
	abstract public function make(): self;

	/**
	 * Check if the store has data by cache key.
	 *
	 * @since  1.0.0
	 */
	abstract public function has( string $key ): bool;

	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 * @return mixed|null
	 */
	abstract public function get( string $key );

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	abstract public function put( string $key, $data, int $seconds = 0 ): bool;

	/**
	 * Writes new data if it doesn't exist by cache key.
	 *
	 * @since  1.0.0
	 */
	abstract public function add( string $key, $data, int $seconds = 0 ): bool;

	/**
	 * Deletes data if it exists by cache key.
	 *
	 * @since  1.0.0
	 */
	abstract public function forget( string $key ): bool;

	/**
	 * Writes new data if it doesn't exist by cache key. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	abstract public function forever( string $key, $data ): bool;

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	abstract public function remember( string $key, int $seconds, Closure $callback );

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	abstract public function rememberForever( string $key, Closure $callback );

	/**
	 * Deletes all cached data from a store.
	 *
	 * @since  1.0.0
	 */
	abstract public function flush(): void;

	/**
	 * Gets and returns data by key. Deletes previous data.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	abstract public function pull( string $key );

	/**
	 * Helper function for creating an expiration time when added to the
	 * current time.  If set to `0`, we just send that back.
	 *
	 * @since  1.0.0
	 */
	protected function availableAt( int $seconds = 0 ): int
	{
		return 0 === $seconds ? 0 : $seconds + time();
	}

	/**
	 * Check's if a store's data is set by key.
	 *
	 * @since  1.0.0
	 */
	protected function hasData( string $key ): bool
	{
		return isset( $this->data[ $key ] );
	}

	/**
	 * Returns store's data by key if it is set.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	protected function getData( string $key )
	{
		return $this->data[ $key ]['data'];
	}

	/**
	 * Sets a store's data by key.
	 *
	 * @since  1.0.0
	 */
	protected function setData( string $key, $data ): void
	{
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) ) {
			$data = [
				'meta' => [ 'expires' => 0 ],
				'data'  => $data
			];
		}

		$this->data[ $key ] = $data;
	}

	/**
	 * Removes store's data by key if set.
	 *
	 * @since  1.0.0
	 */
	protected function removeData( string $key ): void
	{
		unset( $this->data[ $key ] );
	}

	/**
	 * Resets store's data.
	 *
	 * @since  1.0.0
	 */
	protected function resetData(): void
	{
		$this->data = [];
	}
}
