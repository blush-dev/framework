<?php
/**
 * Content types collection.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Types;

use Blush\Tools\Collection;

class Types extends Collection {

	/**
	 * Stores types by path.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $paths = [];

	/**
	 * Adds a custom content type.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  array   $options
	 * @return void
	 */
	public function add( $name, $options = [] ) {
		parent::add( $name, new Type( $name, $options ) );
	}

	/**
	 * Gets a custom post type by its path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @return Type|false
	 */
	public function getTypeFromPath( string $path ) {

		if ( ! $this->paths ) {
			foreach ( $this->all() as $type ) {
				$this->paths[ $type->path() ] = $type;
			}
		}

		return $this->paths[ $path ] ?? false;
	}

	/**
	 * Sorts types by their path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function sortByPath() {
		$paths  = [];
		$sorted = [];

		foreach ( $this->all() as $type ) {
			$paths[] = $type->path();
		}

		asort( $paths );

		foreach ( $paths as $path ) {
			$sorted[] = $this->getTypeFromPath( $path );
		}

		return $sorted;
	}
}
