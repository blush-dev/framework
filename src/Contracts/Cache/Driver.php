<?php
/**
 * Cache driver interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Cache;

use Closure;

interface Driver
{
	/**
	 * Returns the store name.
	 *
	 * @since  1.0.0
	 */
	public function store(): string;

	/**
	 * Check if the store has data by cache key.
	 *
	 * @since  1.0.0
	 */
	public function has( string $key ): bool;

	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 */
	public function get( string $key ): mixed;

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 */
	public function put( string $key, mixed $data, int $seconds = 0 ): bool;

	/**
	 * Writes new data if it doesn't exist by cache key.
	 *
	 * @since  1.0.0
	 */
	public function add( string $key, mixed $data, int $seconds = 0 ): bool;

	/**
	 * Deletes data if it exists by cache key.
	 *
	 * @since  1.0.0
	 */
	public function forget( string $key ): bool;

	/**
	 * Writes new data if it doesn't exist by cache key. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function forever( string $key, $data ): bool;

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it.
	 *
	 * @since  1.0.0
	 */
	public function remember( string $key, int $seconds, Closure $callback ): mixed;

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function rememberForever( string $key, Closure $callback ): mixed;

	/**
	 * Deletes all cached data from a store.
	 *
	 * @since  1.0.0
	 */
	public function flush(): void;

	/**
	 * Gets and returns data by key. Deletes previous data.
	 *
	 * @since  1.0.0
	 */
	public function pull( string $key ): mixed;

	/**
	 * Returns the timestamp for when a dataset was created.
	 *
	 * @since  1.0.0
	 */
	public function created( string $key ): ?int;

	/**
	 * Returns the timestamp for when a dataset expires.
	 *
	 * @since  1.0.0
	 */
	public function expires( string $key ): ?int;

	/**
	 * Determines if a dataset has expired.
	 *
	 * @since  1.0.0
	 */
	public function expired( string $key ): bool;
}
