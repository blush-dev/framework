<?php
/**
 * HTML file store implementation.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache\Driver;

use Blush\Tools\Str;

class HtmlFile extends File
{
	/**
	 * File extenstion for the store's files without the preceding dot.
	 *
	 * @since  1.0.0
	 */
	protected string $extension = 'html';

	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 * @return string|null
	 */
	public function get( string $key )
	{
		if ( $this->hasData( $key ) ) {
			return $this->data[ $key ];
		}

		if ( $this->fileExists( $key ) ) {
			if ( $data = file_get_contents( $this->filepath( $key ) ) ) {
				$this->setData( $key, $data );
				return $this->data[ $key ];
			}
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
		$put = file_put_contents( $this->filepath( $key ), $data );

		if ( true === $put ) {
			$this->setData( $key, $data );
		}

		return false;
	}
}
