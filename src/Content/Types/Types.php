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
	 * Stores types by URI.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $uris = [];

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
	 * Gets a custom content type by its path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @return Type|false
	 */
	public function getTypeFromPath( string $path ) {

		// If there is no path, this is a page.
		if ( '' === $path ) {
			return $this->get( 'page' );
		}

		// If paths are not stored, loop through all and store them in
		// the `$paths` property.
		if ( ! $this->paths ) {
			foreach ( $this->all() as $type ) {
				$this->paths[ $type->path() ] = $type;
			}
		}

		// Return the type if the path matches. Else, false.
		return $this->paths[ $path ] ?? false;
	}

	/**
	 * Gets a custom content type by its URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $uri
	 * @return Type|false
	 */
	public function getTypeFromUri( string $uri ) {

		// If there is no URI, this is a page.
		if ( '' === $uri ) {
			return $this->get( 'page' );
		}

		// If URIs are not stored, loop through all and store them in
		// the `$uris` property.
		if ( ! $this->uris ) {
			foreach ( $this->all() as $type ) {
				$this->uris[ $type->uri() ] = $type;
			}
		}

		// Return the type if the URI matches. Else, false.
		return $this->uris[ $uri ] ?? false;
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

		// Gets all the type paths.
		foreach ( $this->all() as $type ) {
			$paths[] = $type->path();
		}

		// Sort paths alphabetically.
		asort( $paths );

		// Loop through each of the paths and get its type.
		foreach ( $paths as $path ) {
			$sorted[] = $this->getTypeFromPath( $path );
		}

		return $sorted;
	}
}
