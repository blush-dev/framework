<?php
/**
 * Creates a virtual content entry.
 *
 * Developers can pass an array of data to the constructor with keys matching
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

use Blush\App;
use Blush\Contracts\Content\ContentType;

class Virtual extends Entry
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( array $data = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$this->$key = $data[ $key ];
			}
		}
	}

	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): ContentType
	{
		return App::get( 'content.types' )->get( 'virtual' );
	}

	/**
	 * Returns the entry name (slug).
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		return '';
	}

	/**
	 * Returns the entry URL.
	 *
	 * @since  1.0.0
	 */
	public function url(): string
	{
		return '';
	}
}
