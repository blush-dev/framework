<?php
/**
 * Collection file store implementation.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache\Driver;

use Blush\Tools\Collection as Collect;
use Blush\Tools\Str;

class CollectionFile extends JsonFile
{
	/**
	 * Returns data from a store by cache key.
	 *
	 * @since  1.0.0
	 * @return Collect|null
	 */
	public function get( string $key )
	{
		if ( $this->hasData( $key ) ) {
			return $this->data[ $key ];
		}

		if ( $data = $this->getJsonFileContents( $key ) ) {
			$this->setData( $key, new Collect( $data ) );
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
		if ( $data instanceof Collect ) {
			$data = $data->all();
		}

		$put = $this->putJsonFileContents( $key, $data );

		if ( true === $put ) {
			$this->setData( $key, $data );
		}

		return $put;
	}
}
