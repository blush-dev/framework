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
 * @since  1.0.0
 * @access public
 */
class Collection extends ArrayObject implements JsonSerializable
{
	/**
	 * Add an item.
	 *
	 * @since  1.0.0
	 * @param  mixed  $name
	 * @param  mixed  $value
	 */
	public function add( $name, $value ) : void
	{
		$this->offsetSet( $name, $value );
	}

	/**
	 * Removes an item.
	 *
	 * @since  1.0.0
	 * @param  mixed  $name
	 */
	public function remove( $name ) : void
	{
		$this->offsetUnset( $name );
	}

	/**
	 * Checks if an item exists.
	 *
	 * @since  1.0.0
	 * @param  mixed  $name
	 */
	public function has( $name ) : bool
	{
		return $this->offsetExists( $name );
	}

	/**
	 * Returns an item.
	 *
	 * @since  1.0.0
	 * @param  mixed  $name
	 */
	public function get( $name )
	{
		return $this->offsetGet( $name );
	}

	/**
	 * Returns the collection of items.
	 *
	 * @since 1.0.0
	 */
	public function all() : array
	{
		return $this->getArrayCopy();
	}

	/**
	 * Magic method when trying to set a property. Assume the property is
	 * part of the collection and add it.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  mixed   $value
	 */
	public function __set( $name, $value ) : void
	{
		$this->offsetSet( $name, $value );
	}

	/**
	 * Magic method when trying to unset a property.
	 *
	 * @since  1.0.0
	 * @param  string $name
	 * @param  mixed  $value
	 */
	public function __unset( $name ) : void
	{
		$this->offsetUnset( $name );
	}

	/**
	 * Magic method when trying to check if a property has.
	 *
	 * @since  1.0.0
	 * @param  string $name
	 */
	public function __isset( $name ) : bool
	{
		return $this->offsetExists( $name );
	}

	/**
	 * Magic method when trying to get a property.
	 *
	 * @since  1.0.0
	 * @param  string $name
	 * @return mixed
	 */
	public function __get( $name )
	{
		return $this->offSetGet( $name );
	}

	/**
	 * Returns a JSON-ready array of data.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function jsonSerialize()
	{
		return array_map( function( $value ) {

			if ( $value instanceof JsonSerializable ) {
				return $value->jsonSerialize();
			}

			return $value;

		}, $this->all() );
	}
}
