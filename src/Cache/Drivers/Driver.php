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

namespace Blush\Cache\Drivers;

use Closure;
use Blush\Contracts\Cache\Driver as DriverContract;
use Blush\Contracts\Makeable;

abstract class Driver implements DriverContract, Makeable
{
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
	public function __construct( protected string $store ) {}

	/**
	 * Returns the store name.
	 *
	 * @since  1.0.0
	 */
	public function store(): string
	{
		return $this->store;
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
	 */
	abstract public function get( string $key ): mixed;

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 */
	abstract public function put( string $key, mixed $data, int $seconds = 0 ): bool;

	/**
	 * Writes new data if it doesn't exist by cache key.
	 *
	 * @since  1.0.0
	 */
	abstract public function add( string $key, mixed $data, int $seconds = 0 ): bool;

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
	 */
	abstract public function remember( string $key, int $seconds, Closure $callback ): mixed;

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	abstract public function rememberForever( string $key, Closure $callback ): mixed;

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
	 */
	abstract public function pull( string $key ): mixed;

	/**
	 * Returns the timestamp for when a dataset was created. Returns `null`
	 * if the data has not been set.
	 *
	 * @since  1.0.0
	 */
	public function created( string $key ): ?int
	{
		if ( ! $this->hasData( $key ) ) {
			return null;
		}

		return $this->data[$key]['meta']['created'] ?? 0;
	}

	/**
	 * Returns the timestamp for when a dataset expires. Returns `null` if
	 * the data has not been set.
	 *
	 * @since  1.0.0
	 */
	public function expires( string $key ): ?int
	{
		if ( ! $this->hasData( $key ) ) {
			return null;
		}

		return $this->data[$key]['meta']['expires'] ?? 0;
	}

	/**
	 * Determines if a dataset has expired by key.  This will only return
	 * accurately if the data is set.
	 *
	 * @since  1.0.0
	 */
	public function expired( string $key ): bool
	{
		$expires = $this->expires( $key );

		// If the expiration is set to 0, then it is set to `forever`,
		// and the data should only be manually flushed.
		//
		// If expiration is `null`, assume it doesn't expire, but this
		// is likely that the method was called before data was set.
		if ( 0 === $expires || is_null( $expires ) ) {
			return false;
		}

		// If the current time is greater than the expiration, then the
		// data has expired.
		return time() > $expires;
	}

	/**
	 * Determines if a dataset has expired by checking within the dataset
	 * itself. This should be used internally over the `expired()` method if
	 * access to the data is already available.
	 *
	 * @since  1.0.0
	 */
	protected function hasExpired( array $data ): bool
	{
		// If no metadata is set, assume it does not expire.
		if ( ! isset( $data['meta'] ) && ! isset( $data['meta']['expires'] ) ) {
			return false;
		}

		$expires = abs( intval( $data['meta']['expires'] ) );

		// If the expiration is set to 0, then it is set to `forever`,
		// and the data should only be manually flushed.
		if ( 0 === $expires ) {
			return false;
		}

		// If the current time is greater than the expiration, then the
		// data has expired.
		return time() > $expires;
	}

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
	 * Helper function for adding a created timestamp.
	 *
	 * @since  1.0.0
	 */
	protected function createdAt(): int
	{
		return time();
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
	 */
	protected function getData( string $key ): mixed
	{
		return $this->data[ $key ]['data'];
	}

	/**
	 * Sets a store's data by key.
	 *
	 * @since  1.0.0
	 */
	protected function setData( string $key, mixed $data ): void
	{
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) ) {
			$data = [
				'meta' => [
					'expires' => $this->availableAt(),
					'created' => $this->createdAt()
				],
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
