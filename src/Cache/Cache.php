<?php
/**
 * Base cache class.
 *
 * This file houses a collection of static methods for working with strings.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

use Blush\Proxies\App;
use Blush\Tools\Collection;
use Blush\Tools\Str;

abstract class Cache {

	/**
	 * Cache name.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    mixed
	 */
	protected $name;

	/**
	 * Cache directory path.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    mixed
	 */
	protected $path;

	/**
	 * Houses cached data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    mixed
	 */
	protected $data;

	/**
	 * Sets up object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  string  $path
	 * @return void
	 */
	public function __construct( $name, $path = ''  ) {
		$this->name = Str::afterLast( $name, '/' );
		$this->path = Str::appendPath(
			App::resolve( 'path.cache' ),
			Str::beforeLast( $name, '/' )
		);
	}

	/**
	 * Sub-classes should append the appropriate file extension to the
	 * `$this->name` property using the `$this->path()` method.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return string
	 */
	abstract protected function filename();

	/**
	 * Returns the cache directory path.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string  $file
	 * @return string
	 */
	protected function path( string $file = '' ) {
		return $file ? Str::appendPath( $this->path, $file ) : $this->path;
	}

	/**
	 * Makes the cache directory path if it doesn't exist.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function make() {
		if ( ! file_exists( $this->path() ) ) {
			mkdir( $this->path(), 0775, true );
		}
	}

	/**
	 * Returns the cached data.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return mixed
	 */
	public function get() {
		return $this->data ?: null;
	}

	/**
	 * Sets the cached data. Child classes should write the file contents
	 * here. Otherwise, the data is only cached for a single page load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  mixed  $data
	 * @return void
	 */
	public function set( $data ) {
		$this->make();
		$this->data = $data;
	}

	/**
	 * Deletes cache.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function delete() {
		if ( file_exists( $this->filename() ) ) {
			unlink( $this->filename() );
		}
	}
}
