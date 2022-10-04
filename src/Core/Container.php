<?php
/**
 * Container class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionUnionType;
use Blush\Contracts\Core\Container as ContainerContract;

class Container implements ContainerContract, ArrayAccess
{
	/**
	* Stored definitions of objects.
	*
	* @since 1.0.0
	*/
	protected array $bindings = [];

	/**
	 * Array of aliases for bindings.
	 *
	 * @since 1.0.0
	 */
	protected array $aliases = [];

	/**
	* Array of single instance objects.
	*
	* @since 1.0.0
	*/
	protected array $instances = [];

	/**
	* Array of object extensions.
	*
	* @since 1.0.0
	*/
	protected array $extensions = [];

	/**
	 * Array of service provider objects.
	 *
	 * @since 1.0.0
	 */
	protected array $providers = [];

	/**
	 * Array of static proxy classes and aliases.
	 *
	 * @since 1.0.0
	 */
	protected array $proxies = [];

	/**
	* Set up a new container.
	*
	* @since 1.0.0
	*/
	public function __construct( array $definitions = [] )
	{
		foreach ( $definitions as $abstract => $concrete ) {
			$this->add( $abstract, $concrete );
		}
	}

	/**
	 * Add a binding. The abstract should be a key, abstract class name, or
	 * interface name. The concrete should be the concrete implementation of
	 * the abstract. If no concrete is given, its assumed the abstract
	 * handles the concrete implementation.
	 *
	 * @since  1.0.0
	 */
	public function bind( string $abstract, mixed $concrete = null, bool $shared = false ): void
	{
		unset( $this->instances[ $abstract ] );

		if ( is_null( $concrete ) ) {
			$concrete = $abstract;
		}

		$this->bindings[ $abstract ]   = compact( 'concrete', 'shared' );
		$this->extensions[ $abstract ] = [];
	}

	/**
	* Alias for `bind()`.
	*
	* @since  1.0.0
	*/
	public function add( string $abstract, mixed $concrete = null, bool $shared = false ): void
	{
		$this->bind( $abstract, $concrete, $shared );
	}

	/**
	 * Remove a binding.
	 *
	 * @since 1.0.0
	 */
	public function remove( string $abstract ): void
	{
		if ( $this->has( $abstract ) ) {
			unset( $this->bindings[ $abstract ], $this->instances[ $abstract ] );
		}
	}

	/**
	 * Resolve and return the binding.
	 *
	 * @since  1.0.0
	 */
	public function resolve( string $abstract, array $parameters = [] ): mixed
	{
		// Get the true abstract name.
		$abstract = $this->getAbstract( $abstract );

		// If this is being managed as an instance and we already have
		// the instance, return it now.
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		// Get the concrete implementation.
		$concrete = $this->getConcrete( $abstract );

		// If we can't build an object, assume we should return the value.
		if ( ! $this->isBuildable( $concrete ) ) {

			// If we don't actually have this, return false.
			if ( ! $this->has( $abstract ) ) {
				return false;
			}

			return $concrete;
		}

		// Build the object.
		$object = $this->build( $concrete, $parameters );

		if ( ! $this->has( $abstract ) ) {
			return $object;
		}

		// If shared instance, make sure to store it in the instances
		// array so that we're not creating new objects later.
		if ( $this->bindings[ $abstract ]['shared'] && ! isset( $this->instances[ $abstract ] ) ) {
			$this->instances[ $abstract ] = $object;
		}

		// Run through each of the extensions for the object.
		foreach ( $this->extensions[ $abstract ] as $extension ) {
			$object = $extension( $object, $this );
		}

		// Return the object.
		return $object;
	}

	/**
	 * Alias for `resolve()`.
	 *
	 * @since  1.0.0
	 */
	public function make( string $abstract, array $parameters = [] ): mixed
	{
		return $this->resolve( $abstract, $parameters );
	}

	/**
	* Alias for `resolve()`.
	*
	* @since  1.0.0
	*/
	public function get( string $abstract ): mixed
	{
		return $this->resolve( $abstract );
	}

	/**
	 * Creates an alias for an abstract. This allows you to add names that
	 * are easy to access without remembering more complex class names.
	 *
	 * @since 1.0.0
	 */
	public function alias( string $abstract, string $alias ): void
	{
		$this->aliases[ $alias ] = $abstract;
	}

	/**
	* Check if a binding exists.
	*
	* @since 1.0.0
	*/
	public function has( string $abstract ): bool
	{
		return isset( $this->bindings[ $abstract ] ) || isset( $this->instances[ $abstract ] );
	}

	/**
	 * Add a shared binding.
	 *
	 * @since  1.0.0
	 */
	public function singleton( string $abstract, mixed $concrete = null ): void
	{
		$this->add( $abstract, $concrete, true );
	}

	/**
	 * Add an existing instance. This can be an instance of an object or a
	 * single value that should be stored.
	 *
	 * @since  1.0.0
	 */
	public function instance( string $abstract, mixed $instance ): mixed
	{
		return $this->instances[ $abstract ] = $instance;
	}

	/**
	 * Extend a binding with something like a decorator class. Cannot
	 * extend resolved instances.
	 *
	 * @since  1.0.0
	 */
	public function extend( string $abstract, Closure $closure ): void
	{
		$abstract = $this->getAbstract( $abstract );

		$this->extensions[ $abstract ][] = $closure;
	}

	/**
	 * Checks if we're dealing with an alias and returns the abstract. If
	 * not an alias, return the abstract passed in.
	 *
	 * @since 1.0.0
	 */
	protected function getAbstract( string $abstract ): string
	{
		if ( isset( $this->aliases[ $abstract ] ) ) {
			return $this->aliases[ $abstract ];
		}

		return $abstract;
	}

	/**
	 * Gets the concrete of an abstract.
	 *
	 * @since  1.0.0
	 */
	protected function getConcrete( string $abstract ): mixed
	{
		$concrete = false;
		$abstract = $this->getAbstract( $abstract );

		if ( $this->has( $abstract ) ) {
			$concrete = $this->bindings[ $abstract ]['concrete'];
		}

		return $concrete ?: $abstract;
	}

	/**
	 * Determines if a concrete is buildable. It should either be a closure
	 * or a concrete class.
	 *
	 * @since  1.0.0
	 */
	protected function isBuildable( mixed $concrete ): bool
	{
		return $concrete instanceof Closure ||
		       ( is_string( $concrete ) && class_exists( $concrete ) );
	}

	/**
	 * Builds the concrete implementation. If a closure, we'll simply return
	 * the closure and pass the included parameters. Otherwise, we'll resolve
	 * the dependencies for the class and return a new object.
	 *
	 * @since  1.0.0
	 */
	protected function build( mixed $concrete, array $parameters = [] ): mixed
	{
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $parameters );
		}

		$reflect = new ReflectionClass( $concrete );

		$constructor = $reflect->getConstructor();

		if ( ! $constructor ) {
			return new $concrete();
		}

		return $reflect->newInstanceArgs(
			$this->resolveDependencies( $constructor->getParameters(), $parameters )
		);
	}

	/**
	 * Resolves the dependencies for a method's parameters.
	 *
	 * @todo  Handle errors when we can't solve a dependency.
	 * @since 1.0.0
	 */
	protected function resolveDependencies( array $dependencies, array $parameters ): array
	{
		$args = [];

		foreach ( $dependencies as $dependency ) {

			// If a dependency is set via the parameters passed in, use it.
			if ( isset( $parameters[ $dependency->getName() ] ) ) {
				$args[] = $parameters[ $dependency->getName() ];
				continue;
			}

			// If the parameter is a class, resolve it.
			$types = $this->getReflectionTypes( $dependency );

			if ( $types ) {
				$resolved_type = false;

				foreach ( $types as $type ) {
					$name = $type->getName();

					if (
						class_exists( $name ) ||
						interface_exists( $name )
					) {
						$args[] = $this->resolve( $name );
						$resolved_type = true;
					}
				}

				if ( $resolved_type ) {
					continue;
				}
			}

			// Else, use the default parameter value.
			if ( $dependency->isDefaultValueAvailable() ) {
				$args[] = $dependency->getDefaultValue();
			}
		}

		return $args;
	}

	/**
	 * `ReflectionParameter::getType()` in PHP may return an instance of
	 * `ReflectionNamedType` or an `ReflectionUnionType`.  The latter class's
	 * `getTypes()` method returns an array of the former objects. This
	 * method ensures that we always get an array of `ReflectionNamedType`
	 * objects.
	 *
	 * @since  1.0.0
	 */
	protected function getReflectionTypes( ReflectionParameter $dependency ): array
	{
		$types = $dependency->getType();

		if ( ! $types ) {
			return [];
		} elseif ( class_exists( 'ReflectionUnionType' ) && $types instanceof ReflectionUnionType ) {
			return $types->getTypes();
		}

		return [ $types ];
	}

	/**
	 * Adds a service provider. All service providers must extend the
	 * `ServiceProvider` class. A string or an instance of the provider may
	 * be passed in.
	 *
	 * @since  1.0.0
	 */
	public function provider( ServiceProvider|string $provider ): void
	{
		if ( is_string( $provider ) ) {
			$provider = $this->resolveProvider( $provider );
		}

		$this->providers[] = $provider;
	}

	/**
	 * Creates a new instance of a service provider class.
	 *
	 * @since 1.0.0
	 */
	protected function resolveProvider( string $provider ): ServiceProvider
	{
		return new $provider( $this );
	}

	/**
	 * Calls a service provider's `register()` method.
	 *
	 * @since 1.0.0
	 */
	protected function registerProvider( ServiceProvider $provider ): void
	{
		$provider->register();
	}

	/**
	 * Calls a service provider's `boot()` method.
	 *
	 * @since 1.0.0
	 */
	protected function bootProvider( ServiceProvider $provider ): void
	{
		$provider->boot();
	}

	/**
	 * Returns an array of service providers.
	 *
	 * @since 1.0.0
	 */
	protected function getProviders(): array
	{
		return $this->providers;
	}

	/**
	 * Calls the `register()` method of all the available service providers.
	 *
	 * @since 1.0.0
	 */
	protected function registerProviders(): void
	{
		foreach ( $this->getProviders() as $provider ) {
			$this->registerProvider( $provider );
		}
	}

	/**
	 * Calls the `boot()` method of all the registered service providers.
	 *
	 * @since 1.0.0
	 */
	protected function bootProviders(): void
	{
		foreach ( $this->getProviders() as $provider ) {
			$this->bootProvider( $provider );
		}
	}

	/**
	 * Adds a static proxy alias. Developers must pass in fully-qualified
	 * class name and alias class name.
	 *
	 * @since 1.0.0
	 */
	public function proxy( string $class_name, string $alias ): void
	{
		$this->proxies[ $class_name ] = $alias;
	}

	/**
	 * Registers the static proxy classes.
	 *
	 * @since 1.0.0
	 */
	protected function registerProxies(): void
	{
		Proxy::setContainer( $this );

		foreach ( $this->proxies as $class => $alias ) {
			class_alias( $class, $alias );
		}
	}

	/**
	* Sets a property via `ArrayAccess`.
	*
	* @since  1.0.0
	*/
	public function offsetSet( mixed $name, mixed $value ): void
	{
		$this->add( $name, $value );
	}

	/**
	* Unsets a property via `ArrayAccess`.
	*
	* @since  1.0.0
	*/
	public function offsetUnset( mixed $name ): void
	{
		$this->remove( $name );
	}

	/**
	* Checks if a property exists via `ArrayAccess`.
	*
	* @since  1.0.0
	*/
	public function offsetExists( mixed $name ): bool
	{
		return $this->has( $name );
	}

	/**
	* Returns a property via `ArrayAccess`.
	*
	* @since  1.0.0
	*/
	public function offsetGet( mixed $name ): mixed
	{
		return $this->get( $name );
	}

	/**
	* Magic method when trying to set a property.
	*
	* @since  1.0.0
	*/
	public function __set( string $name, mixed $value ): void
	{
		$this->add( $name, $value );
	}

	/**
	* Magic method when trying to unset a property.
	*
	* @since  1.0.0
	*/
	public function __unset( string $name ): void
	{
		$this->remove( $name );
	}

	/**
	* Magic method when trying to check if a property exists.
	*
	* @since  1.0.0
	*/
	public function __isset( string $name ): bool
	{
		return $this->has( $name );
	}

	/**
	* Magic method when trying to get a property.
	*
	* @since  1.0.0
	*/
	public function __get( string $name ): mixed
	{
		return $this->get( $name );
	}
}
