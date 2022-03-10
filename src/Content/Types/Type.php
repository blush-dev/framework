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

	/**
	 * Name of the content type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $type;

	/**
	 * Content type path.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $path = '';

	/**
	 * Array of custom routes.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $routes = [];

	/**
	 * Whether the content type is a taxonomy.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $taxonomy = false;

	/**
	 * The content type that this content type collects in archives.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $collect = null;

	/**
	 * If the content type is a taxonomy, the content type that the
	 * taxonomy terms collect.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $term_collect = null;

	/**
	 * Sets up the object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $type
	 * @param  array   $options
	 * @return void
	 */
	public function __construct( string $type, array $options = [] ) {

		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
		}

		if ( is_null( $this->collect ) ) {
			$this->collect = $type;
		}

		if ( $this->routes ) {
			$_routes = [];
			foreach ( $this->routes as $route => $args ) {
				$_routes[ $route ] = is_string( $args )
					? [ 'controller' => $args ]
					: $args;
			}
			$this->routes = $_routes;
		}

		$this->type = $type;
	}

	/**
	 * Returns the content type name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * Returns the content type path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function path() {
		return $this->path;
	}

	/**
	 * Returns the content type path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function routes() {
		return $this->routes;
	}

	/**
	 * Returns the type this content type collects.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function collect() {
		return $this->collect;
	}

	/**
	 * Returns the type that terms of this type collects if a taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function termCollect() {
		return $this->term_collect;
	}

	/**
	 * Whether this type is a taxonomy.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return bool
	 */
	public function isTaxonomy() {
		return (bool) $this->taxonomy;
	}
}
