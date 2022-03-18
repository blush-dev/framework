<?php
/**
 * JSON file store implementation.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache\Driver;

use Blush\Tools\Str;

class JsonFile extends File
{
	/**
	 * File extenstion for the store's files without the preceding dot.
	 *
	 * @since  1.0.0
	 */
	protected string $extension = 'json';

	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 * @return array|null
	 */
	public function get( string $key )
	{
		if ( $this->hasData( $key ) ) {
			return $this->data[ $key ];
		}

		if ( $data = $this->getJsonFileContents( $key ) ) {
			$this->setData( $key, $data );
			return $this->data[ $key ];
		}

		return $this->data[ $key ] = null;
	}

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	public function put( string $key, $data, int $expire = 0 ): bool
	{
		$put = $this->putJsonFileContents( $key, $data );

		if ( true === $put ) {
			$this->setData( $key, $data );
		}

		return $put;
	}

	/**
	 * Gets the cache file contents by key and runs it through `json_decode()`.
	 *
	 * @since  1.0.0
	 * @return array|false
	 */
	protected function getJsonFileContents( string $key )
	{
		if ( ! $this->fileExists( $key ) ) {
			return false;
		}

		$contents = file_get_contents( $this->filepath( $key ) );

		$decoded = $contents ? json_decode( $contents, true ) : false;

		return $decoded ?: false;
	}

	/**
	 * Encodes an array of data to JSON and writes it to the file path.
	 *
	 * @since  1.0.0
	 */
	protected function putJsonFileContents( string $key, array $data ): bool
	{
		$data = preg_replace(
			[
				"/\n\s\s\s\s\s\s\s\s\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s/"
			],
			[
				"\n\t\t\t\t",
				"\n\t\t\t",
				"\n\t\t",
				"\n\t"
			],
			json_encode( $data, JSON_PRETTY_PRINT )
		);

		$put = file_put_contents( $this->filepath( $key ), $data );

		return false !== $put;
	}
}
