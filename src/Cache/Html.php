<?php
/**
 * HTML cache class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

class Html extends Cache {

	/**
	 * Returns the cache filename.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return string
	 */
	protected function filename() {
		return $this->path( "{$this->name}.html" );
	}

	/**
	 * Sets the cached data. Child classes should write the file contents
	 * here. Otherwise, the data is only cached for a single page load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  mixed  $data
	 * @return void
	 */
	public function set( $data ) {
		$this->make();
		$this->data = $data;

		file_put_contents( $this->filename(), $this->data );
	}

	/**
	 * Returns the cached data.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return mixed
	 */
	public function get() {
		if ( $this->data ) {
			return $this->data;
		}

		if ( file_exists( $this->filename() ) ) {
			$this->data = file_get_contents( $this->filename() );
			return $this->data;
		}

		$this->data = '';

		return $this->data;
	}
}
