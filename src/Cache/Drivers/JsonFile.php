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

namespace Blush\Cache\Drivers;

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
	 */
	public function get( string $key ): mixed
	{
		if ( $this->hasData( $key ) ) {
			return $this->getData( $key );
		}

		if ( $data = $this->getJsonFileContents( $key ) ) {

			if ( $this->hasExpired( $data ) ) {
				$this->forget( $key );
				return null;
			}

			$this->setData( $key, $data );
		}

		return $this->data[$key]['data'] ?? null;
	}

	/**
	 * Writes new data or replaces existing data by cache key.
	 *
	 * @since  1.0.0
	 */
	public function put( string $key, mixed $data, int $seconds = 0 ): bool
	{
		$put = $this->putJsonFileContents( $key, $data, $seconds );

		if ( true === $put ) {
			$this->setData( $key, $data );
		}

		return $put;
	}

	/**
	 * Gets the cache file contents by key and runs it through `json_decode()`.
	 *
	 * @since  1.0.0
	 */
	protected function getJsonFileContents( string $key ): array|false
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
	protected function putJsonFileContents( string $key, array $data, int $seconds ): bool
	{
		$data = json_encode( [
			'meta' => [
				'expires' => $this->availableAt( $seconds )
			],
			'data' => $data
		], JSON_PRETTY_PRINT );

		$put = file_put_contents( $this->filepath( $key ), $data );

		return false !== $put;
	}
}
