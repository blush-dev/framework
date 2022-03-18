<?php
/**
 * File store implementation.
 *
 * This class serializes and stores data to the filesystem by key. The filepaths
 * are built by path, key, and extension (e.g., `{$path}/{$key}.{$extension}`).
 * Sub-classes can expand on this for various data and file types.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache\Driver;

use Closure;
use Blush\Tools\Str;

class File extends Store
{
	/**
	 * The store's full directory path.
	 *
	 * @since  1.0.0
	 */
	protected string $path = '';

	/**
	 * File extenstion for the store's files without the preceding dot.
	 *
	 * @since  1.0.0
	 */
	protected string $extension = 'cache';

	/**
	 * Creates the store directory.
	 *
	 * @since  1.0.0
	 */
	public function make(): self
	{
		if ( ! file_exists( $this->path() ) ) {
			mkdir( $this->path(), 0775, true );
		}

		return $this;
	}

	/**
	 * Returns the store's full directory path.
	 *
	 * @since  1.0.0
	 */
	public function path(): string
	{
		return $this->path;
	}

	/**
	 * Returns a store cache's filepath.
	 *
	 * @since  1.0.0
	 */
	public function filepath( string $key ): string
	{
		$ext = trim( $this->extension, '.' );
		return Str::appendPath( $this->path(), "{$key}.{$ext}" );
	}

	/**
	 * Checks if a store cache's filepath exists.
	 *
	 * @since  1.0.0
	 */
	protected function fileExists( string $key ): bool
	{
		return file_exists( $this->filepath( $key ) );
	}

	/**
	 * Check if the store has data by cache key.
	 *
	 * @since  1.0.0
	 */
	public function has( string $key ): bool
	{
		return $this->hasData( $key ) || $this->fileExists( $key );
	}

	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 * @return mixed|null
	 */
	public function get( string $key )
	{
		if ( $this->hasData( $key ) ) {
			return $this->data[ $key ];
		}

		if ( $this->fileExists( $key ) ) {
			$data = file_get_contents( $this->filepath( $key ) );
			$this->setData( $key, unserialize( $data ) );
		}

		return $this->data[ $key ] ?? null;
	}

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	public function put( string $key, $data, int $expire = 0 ): bool
	{
		$data = serialize( $data );
		$put  = file_put_contents( $this->filepath( $key ), $data );

		if ( true === $put ) {
			$this->setData( $key, $data );
		}

		return false;
	}

	/**
	 * Writes new data if it doesn't exist by cache key.
	 *
	 * @since  1.0.0
	 */
	public function add( string $key, $data, int $expire = 0 ): bool
	{
		if ( ! $this->fileExists( $key ) ) {
			return $this->put( $key, $data, $expire );
		}

		return false;
	}

	/**
	 * Deletes data if it exists by cache key.
	 *
	 * @since  1.0.0
	 */
	public function forget( string $key ): bool
	{
		if ( $this->fileExists( $key ) ) {
			$this->removeData( $key );
			return unlink( $this->filepath() );
		}

		return false;
	}

	/**
	 * Writes new data if it doesn't exist by cache key. Doesn't expire.
	 *
	 * @since  1.0.0
	 */
	public function forever( string $key, $data ): bool
	{
		return $this->add( $key, $data, 0 );
	}

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function remember( string $key, int $expire = 0, Closure $callback )
	{
		$data = $this->get( $key );

		if ( ! $data ) {
			$data = $callback();
			if ( $data ) {
				$this->put( $key, $data, $expire );
			}
		}

		return $data;
	}

	/**
	 * Gets and returns data by cache key. If it doesn't exist, callback is
	 * executed to pass in custom data and write it. Doesn't expire.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function rememberForever( string $key, Closure $callback )
	{
		return $this->remember( $key, 0, $callback );
	}

	/**
	 * Deletes all cached data from a store.
	 *
	 * @since  1.0.0
	 */
	public function flush(): void
	{
		$ext = trim( $this->extension, '.' );
		$search = Str::appendPath( $this->path(), "*.{$ext}" );

		foreach ( glob( $search ) as $filepath ) {
			unlink( $filepath );
			$this->resetData();
		}
	}

	/**
	 * Gets and returns data by key. Deletes previous data.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function pull( string $key )
	{
		$data = $this->get( $key );

		$this->forget( $key );

		return $data;
	}
}
