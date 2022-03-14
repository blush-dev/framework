<?php
/**
 * Container contract.
 *
 * Container classes should be used for storing, retrieving, and resolving
 * classes/objects passed into them.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Container;

use Closure;

interface Container
{
	/**
	 * Add a binding. The abstract should be a key, abstract class name, or
	 * interface name. The concrete should be the concrete implementation of
	 * the abstract.
	 *
	 * @since  1.0.0
	 * @param  mixed  $concrete
	 */
	public function bind(
		string $abstract,
		$concrete = null,
		bool $shared = false
	) : void;

	/**
	* Alias for `bind()`.
	*
	* @since  1.0.0
	* @param  mixed  $concrete
	*/
	public function add(
		string $abstract,
		$concrete = null,
		bool $shared = false
	) : void;

	/**
	 * Remove a binding.
	 *
	 * @since 1.0.0
	 */
	public function remove( string $abstract ) : void;

	/**
	 * Resolve and return the binding.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function resolve( string $abstract, array $parameters = [] );

	/**
	 * Alias for `resolve()`.
	 *
	 * Follows the PSR-11 standard. Do not alter.
	 * @link https://www.php-fig.org/psr/psr-11/
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function get( string $abstract );

	/**
	 * Check if a binding exists.
	 *
	 * Follows the PSR-11 standard. Do not alter.
	 * @link https://www.php-fig.org/psr/psr-11/
	 *
	 * @since  1.0.0
	 */
	public function has( $abstract ) : bool;

	/**
	 * Add a shared binding.
	 *
	 * @since  1.0.0
	 * @param  mixed  $concrete
	 */
	public function singleton( string $abstract, $concrete = null ) : void;

	/**
	 * Add an existing instance.
	 *
	 * @since  1.0.0
	 * @param  mixed  $instance
	 * @return mixed
	 */
	 public function instance( string $abstract, $instance );

	 /**
	  * Extend a binding.
	  *
	  * @since 1.0.0
	  */
	 public function extend( string $abstract, Closure $closure ) : void;

	 /**
	  * Create an alias for an abstract type.
	  *
	  * @since 1.0.0
	  */
	 public function alias( string $abstract, string $alias ) : void;
}
