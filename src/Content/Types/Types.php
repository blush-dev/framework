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

	private $paths = [];
	private $taxonomies = [];

	public function add( $name, $options = [] ) {
		parent::add( $name, new Type( $name, $options ) );
	}

	public function getTypeFromPath( $path ) {

		if ( ! $this->paths ) {
			foreach ( $this->all() as $type ) {
				$this->paths[ $type->path() ] = $type;
			}
		}

		return $this->paths[ $path ] ?? false;
	}

	public function sortByPath() {

		$paths = [];
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
