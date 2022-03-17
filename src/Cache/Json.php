<?php
/**
 * JSON cache class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

use Blush\Tools\Collection;

class Json extends Cache
{
	/**
	 * Returns the cache filename.
	 *
	 * @since 1.0.0
	 */
	public function filename() : string
	{
		return $this->path( "{$this->name}.json" );
	}

	/**
	 * Sets the cached data. Child classes should write the file contents
	 * here. Otherwise, the data is only cached for a single page load.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	public function set( $data ) {
		$this->make();

		$this->data = (array) $data;

		$json = preg_replace(
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
			json_encode( $this->data, JSON_PRETTY_PRINT )
		);

		file_put_contents( $this->filename(), $json );
	}

	/**
	 * Returns the cached data.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return mixed
	 */
	public function get()
	{
		if ( $this->data ) {
			return $this->data;
		}

		$filename = file_exists( $this->filename() );
		$contents = $filename ? file_get_contents( $this->filename() ) : '';

		if ( $contents && $decoded = json_decode( $contents, true ) ) {
			return $this->data = $decoded;
		}

		return $this->data = [];
	}
}
