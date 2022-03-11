<?php
/**
 * Creates a virtual content entry.
 *
 * Developers can pass an array of data to the contstructure with keys matching
 * the class properties to set up a virtual entry. This is primarily useful for
 * creating the `$single` entry object for routed URIs that do not exist in the
 * filesystem.  For example, custom date-based archive pages.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

class Virtual extends Entry {

	/**
	 * Sets up the object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $data
	 * @return void
	 */
	public function __construct( array $data = [] ) {

		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$this->$key = $data[ $key ];
			}
		}

		$this->meta = (array) $this->meta;
	}
}
