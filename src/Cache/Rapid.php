<?php
/**
 * Rapid cache class.
 *
 * This is for rapidly-decaying data.  It just caches data for a single page
 * load is not persistent.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

class Rapid extends Cache {

	/**
	 * No filename.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return string
	 */
	protected function filename() {
		return '';
	}

	/**
	 * Don't make a cache directory.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function make() {}

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
		$this->data = $data;
	}
}
