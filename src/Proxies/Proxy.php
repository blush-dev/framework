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

namespace Blush\Proxies;

use Blush\Contracts\Container\Container;

/**
 * Base static proxy class.
 *
 * @since  5.0.0
 * @access public
 */
class Proxy {

	/**
	 * The container object.
	 *
	 * @since  5.0.0
	 * @access protected
	 * @var    Container
	 */
	protected static $container;

	/**
	 * Returns the name of the accessor for object registered in the container.
	 *
	 * @since  5.0.0
	 * @access protected
	 * @return string
	 */
	protected static function accessor() {

		return '';
	}

	/**
	 * Sets the container object.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return void
	 */
	public static function setContainer( Container $container ) {

		static::$container = $container;
	}

	/**
	 * Returns the instance from the container.
	 *
	 * @since  5.0.0
	 * @access protected
	 * @return object
	 */
	protected static function instance() {

		return static::$container->resolve( static::accessor() );
	}

	/**
	 * Calls the requested method from the object registered with the
	 * container statically.
	 *
	 * @since  5.0.0
	 * @access public
	 * @param  string  $method
	 * @param  array   $args
	 * @return mixed
	 */
	public static function __callStatic( $method, $args ) {

		$instance = static::instance();

		return $instance ? $instance->$method( ...$args ) : null;
	}
}
