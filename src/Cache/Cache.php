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

use Blush\App;
use Blush\Contracts\Makeable;
use Blush\Tools\{Collection, Str};

abstract class Cache implements Makeable
{
	/**
	 * Cache name.
	 *
	 * @since 1.0.0
	 */
	protected string $name;

	/**
	 * Cache directory path.
	 *
	 * @since 1.0.0
	 */
	protected string $path;

	/**
	 * Houses cached data.
	 *
	 * @since  1.0.0
	 * @var    mixed
	 */
	protected $data = null;

	/**
	 * Sets up object state. If no `$path` is passed in, `$name` should
	 * include a `/`, which will be split to create the cache name and
	 * path.  Anything after the final `/` becomes the name and anything
	 * before, the path.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $name, string $path = ''  )
	{
		$this->name = Str::afterLast( $name, '/' );
		$this->path = Str::appendPath(
			App::resolve( 'path.cache' ),
			$path ?: Str::beforeLast( $name, '/' )
		);
	}

	/**
	 * Sub-classes should append the appropriate file extension to the
	 * `$this->name` property using the `$this->path()` method.
	 *
	 * @since 1.0.0
	 */
	abstract public function filename() : string;

	/**
	 * Returns the cache directory path.
	 *
	 * @since 1.0.0
	 */
	public function path( string $file = '' ) :string
	{
		return $file ? Str::appendPath( $this->path, $file ) : $this->path;
	}

	/**
	 * Makes the cache directory path if it doesn't exist.
	 *
	 * @since 1.0.0
	 */
	public function make() : self
	{
		if ( ! file_exists( $this->path() ) ) {
			mkdir( $this->path(), 0775, true );
		}

		return $this;
	}

	/**
	 * Returns the cached data.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function get()
	{
		return $this->data ?: null;
	}

	/**
	 * Sets the cached data. Child classes should write the file contents
	 * here. Otherwise, the data is only cached for a single page load.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	public function set( $data ) : void
	{
		$this->make();
		$this->data = $data;
	}

	/**
	 * Deletes cache.
	 *
	 * @since 1.0.0
	 */
	public function delete() : void
	{
		if ( file_exists( $this->filename() ) ) {
			unlink( $this->filename() );
		}
	}
}
