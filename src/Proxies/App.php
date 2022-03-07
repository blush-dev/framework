<?php
/**
 * App static proxy class.
 *
 * Static proxy class for the application instance.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Proxies;

/**
 * App static proxy class.
 *
 * @since  5.0.0
 * @access public
 */
class App extends Proxy {

	/**
	 * Returns the name of the accessor for object registered in the container.
	 *
	 * @since  5.0.0
	 * @access protected
	 * @return string
	 */
	protected static function accessor() {

		return 'app';
	}
}
