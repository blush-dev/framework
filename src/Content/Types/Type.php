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

use Blush\Controllers;
use Blush\Tools\Str;

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
	 * Array of content type routes.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $routes = [];

	/**
	 * Whether routing should be enabled for this post type. Mostly for
	 * internal use with pages.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $routing = true;

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
	 * Stores the URI path for the content type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $uri = '';

	/**
	 * Stores the single entry URI path for the content type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $uri_single = '';

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

		// If the content type doesn't collect another, it should
		// collect itself.
		if ( is_null( $this->collect ) && false !== $this->collect ) {
			$this->collect = $type;
		}

		// Parse routes passed in via `[ $uri => $controller ]` so that
		// they are stored as `$uri => $args`.
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
	 * Returns the content type name (alias for `type()`).
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function name() {
		return $this->type();
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
	 * Returns the content type URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function uri() {
		return $this->uri ?: $this->path();
	}

	/**
	 * Returns the content type URI for single entries.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function singleUri() {
		return $this->uri_single ?: Str::appendUri( $this->path(), '{name}' );
	}

	/**
	 * Whether routing is enabled.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return bool
	 */
	public function routing() {
		return (bool) $this->routing;
	}

	/**
	 * Returns the content type routes as an array.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function routes() {

		// Return empty array of the content type doesn't support routes.
		if ( ! $this->routing() ) {
			return [];
		}

		// If routes are already stored, return them.
		if ( $this->routes ) {
			return $this->routes;
		}

		$path = $this->path();

		// Add paged type archive.
		$this->routes[ $path . '/page/{number}' ] = [
			'controller' => Controllers\ContentTypeArchive::class
		];

		// If this is a taxonomy, add paged term archive.
		if ( $this->isTaxonomy() ) {
			$this->routes[ $path . '/{name}/page/{number}' ] = [
				'controller' => Controllers\TaxonomyTerm::class
			];
		}

		// Add type single route.
		$this->routes[ $path . '/{name}' ] = [
			'controller' => $this->isTaxonomy()
				? Controllers\TaxonomyTerm::class
				: Controllers\Single::class,
			'single' => true
		];

		// Add type archive route.
		$this->routes[ $path ] = [
			'controller' => Controllers\ContentTypeArchive::class
		];

		// Return the built routes.
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
