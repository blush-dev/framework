<?php
/**
 * Collection class.
 *
 * This file houses the `Collection` class, which is a class used for storing
 * collections of data.  Generally speaking, it was built for storing an
 * array of key/value pairs.  Values can be any type of value.  Keys should
 * be named rather than numeric if you need easy access.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Tools;

use ArrayObject;
use JsonSerializable;

/**
 * Registry class.
 *
 * @since  5.0.0
 * @access public
 */
class Collection extends ArrayObject implements JsonSerializable  {

	/**
	 * Add an item.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @param  mixed   $value
	 * @return void
	 */
	public function add( $name, $value ) {

		$this->offsetSet( $name, $value );
	}

	/**
	 * Removes an item.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return void
	 */
	public function remove( $name ) {

		$this->offsetUnset( $name );
	}

	/**
	 * Checks if an item exists.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return bool
	 */
	public function has( $name ) {

		return $this->offsetExists( $name );
	}

	/**
	 * Returns an item.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return mixed
	 */
	public function get( $name ) {

		return $this->offsetGet( $name );
	}

	/**
	 * Returns the collection of items.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return array
	 */
	public function all() {

		return $this->getArrayCopy();
	}

	/**
	 * Magic method when trying to set a property. Assume the property is
	 * part of the collection and add it.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set( $name, $value ) {

		$this->offsetSet( $name, $value );
	}

	/**
	 * Magic method when trying to unset a property.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return void
	 */
	public function __unset( $name ) {

		$this->offsetUnset( $name );
	}

	/**
	 * Magic method when trying to check if a property has.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return bool
	 */
	public function __isset( $name ) {

		return $this->offsetExists( $name );
	}

	/**
	 * Magic method when trying to get a property.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $name
	 * @return mixed
	 */
	public function __get( $name ) {

		return $this->offSetGet( $name );
	}

	/**
	 * Returns a JSON-ready array of data.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function jsonSerialize() {

		return array_map( function( $value ) {

			if ( $value instanceof JsonSerializable ) {
				return $value->jsonSerialize();
			}

			return $value;

		}, $this->all() );
	}
}