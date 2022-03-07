<?php
/**
 * Content type.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Types;

class Type {

	protected $type;
	protected $path = '';
	protected $taxonomy = false;
	protected $collect = null;
	protected $term_collect = null;

	public function __construct( string $type, array $options = [] ) {

		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
		}

		if ( is_null( $this->collect ) ) {
			$this->collect = $type;
		}

		$this->type = $type;
	}

	public function type() {
		return $this->type;
	}

	public function path() {
		return $this->path;
	}

	public function collect() {
		return $this->collect;
	}

	public function termCollect() {
		return $this->term_collect;
	}

	public function isTaxonomy() {
		return $this->taxonomy;
	}

	public function isCollection() {
		return ! is_null( $this->collect );
	}
}
