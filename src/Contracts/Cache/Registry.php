<?php
/**
 * Cache registry interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Cache;

use Closure;

interface Registry
{
	/**
	 * Returns a store driver object or `false`.
	 *
	 * @since  1.0.0
	 */
	public function store( string $store ): Driver|false;

	/**
	 * Returns all stores.
	 *
	 * @since  1.0.0
	 */
	public function getStores(): array;

	/**
	 * Adds a store.
	 *
	 * @since  1.0.0
	 */
	public function addStore( string $name, array $options = [] ): void;

	/**
	 * Removes a store.
	 *
	 * @since  1.0.0
	 */
	public function removeStore( string $store ): void;

	/**
	 * Checks if a store exists.
	 *
	 * @since  1.0.0
	 */
	public function storeExists( string $store ): bool;

	/**
	 * Returns a driver.
	 *
	 * @since  1.0.0
	 */
	public function driver( string $name ): string|false;

	/**
	 * Checks if a driver exists.
	 *
	 * @since  1.0.0
	 */
	public function driverExists( string $name ): bool;

	/**
	 * Adds a driver.
	 *
	 * @since  1.0.0
	 */
	public function addDriver( string $name, string $driver ): void;

	/**
	 * Removes a driver.
	 *
	 * @since  1.0.0
	 */
	public function removeDriver( string $name ): void;

	/**
	 * Check if the store has data via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function has( string $name ): bool;

	/**
	 * Returns data from a store via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function get( string $name ): mixed;

	/**
	 * Writes new data or replaces existing data via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function put( string $name, mixed $data, int $seconds = 0 ): bool;

	/**
	 * Writes new data if it doesn't exist via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function add( string $name, $data, int $seconds = 0 ): void;

	/**
	 * Deletes data if it exists via `store.key`.
	 *
	 * @since  1.0.0
	 */
	public function forget( string $name ): void;

	/**
	 * Writes new data if it doesn't exist via `store.key`. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function forever( string $name, $data ): void;

	/**
	 * Gets and returns data via `store.key`. If it doesn't exist, callback
	 * is executed to pass in custom data and write it.
	 *
	 * @since  1.0.0
	 */
	public function remember( string $name, int $seconds, Closure $callback ): mixed;

	/**
	 * Gets and returns data via `store.key`. If it doesn't exist, callback
	 * is executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function rememberForever( string $name, Closure $callback ): mixed;

	/**
	 * Gets and returns data via `store.key`. Deletes previous data.
	 *
	 * @since  1.0.0
	 */
	public function pull( string $name ): mixed;

	/**
	 * Deletes all cached data from a store.
	 *
	 * @since  1.0.0
	 */
	public function flush( string $store ): void;

	/**
	 * Flushes the cached data from all stores.
	 *
	 * @since  1.0.0
	 */
	public function purge(): void;
}
