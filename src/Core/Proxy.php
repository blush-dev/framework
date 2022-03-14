<?php
/**
 * Static proxy class.
 *
 * The base static proxy class. This allows us to create easy-to-use, static
 * classes around shared objects in the container.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core;

use Blush\Contracts\Container\Container;

class Proxy
{
	/**
	 * The container object.
	 *
	 * @since 1.0.0
	 */
	protected static Container $container;

	/**
	 * Returns the name of the accessor for object registered in the container.
	 *
	 * @since  1.0.0
	 */
	protected static function accessor() : string
	{
		return '';
	}

	/**
	 * Sets the container object.
	 *
	 * @since 1.0.0
	 */
	public static function setContainer( Container $container ) : void
	{
		static::$container = $container;
	}

	/**
	 * Returns the instance from the container.
	 *
	 * @since 1.0.0
	 */
	protected static function instance() : object
	{
		return static::$container->resolve( static::accessor() );
	}

	/**
	 * Calls the requested method from the object registered with the
	 * container statically.
	 *
	 * @since  1.0.0
	 * @param  array   $args
	 * @return mixed
	 */
	public static function __callStatic( string $method, $args )
	{
		$instance = static::instance();
		return $instance ? $instance->$method( ...$args ) : null;
	}
}
