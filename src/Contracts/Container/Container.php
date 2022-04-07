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
use Blush\Core\ServiceProvider;

interface Container
{
	/**
	 * Add a binding. The abstract should be a key, abstract class name, or
	 * interface name. The concrete should be the concrete implementation of
	 * the abstract.
	 *
	 * @since  1.0.0
	 */
	public function bind(
		string $abstract,
		mixed $concrete = null,
		bool $shared = false
	): void;

	/**
	* Alias for `bind()`.
	*
	* @since  1.0.0
	*/
	public function add(
		string $abstract,
		mixed $concrete = null,
		bool $shared = false
	): void;

	/**
	 * Remove a binding.
	 *
	 * @since 1.0.0
	 */
	public function remove( string $abstract ): void;

	/**
	 * Resolve and return the binding.
	 *
	 * @since  1.0.0
	 */
	public function resolve( string $abstract, array $parameters = [] ): mixed;

	/**
	 * Alias for `resolve()`.
	 *
	 * @since  1.0.0
	 */
	public function make( string $abstract, array $parameters = [] ): mixed;

	/**
	 * Alias for `resolve()`.
	 *
	 * Follows the PSR-11 standard. Do not alter.
	 * @link https://www.php-fig.org/psr/psr-11/
	 *
	 * @since  1.0.0
	 */
	public function get( string $abstract ): mixed;

	/**
	 * Check if a binding exists.
	 *
	 * Follows the PSR-11 standard. Do not alter.
	 * @link https://www.php-fig.org/psr/psr-11/
	 *
	 * @since  1.0.0
	 */
	public function has( string $abstract ): bool;

	/**
	 * Add a shared binding.
	 *
	 * @since  1.0.0
	 */
	public function singleton( string $abstract, mixed $concrete = null ): void;

	/**
	 * Add an existing instance.
	 *
	 * @since  1.0.0
	 */
	 public function instance( string $abstract, mixed $instance ): mixed;

	 /**
	  * Extend a binding.
	  *
	  * @since 1.0.0
	  */
	 public function extend( string $abstract, Closure $closure ): void;

	 /**
	  * Create an alias for an abstract type.
	  *
	  * @since 1.0.0
	  */
	 public function alias( string $abstract, string $alias ): void;

	 /**
	  * Adds a service provider. Developers can pass in an object or a fully-
	  * qualified class name.
	  *
	  * @since  1.0.0
	  */
	 public function provider( ServiceProvider|string $provider ): void;

	 /**
	  * Adds a static proxy alias. Developers must pass in fully-qualified
	  * class name and alias class name.
	  *
	  * @since 1.0.0
	  */
	 public function proxy( string $class_name, string $alias ): void;
}
