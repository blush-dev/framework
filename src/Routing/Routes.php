<?php
/**
 * Routes collection.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

use Blush\Tools\Collection;

class Routes extends Collection {

	/**
	 * Add a route.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  mixed   $value
	 * @return void
	 */
	public function add( $uri, $args = [] ) {
		parent::add( $uri, new Route( $uri, $args ) );

		$this->get( $uri )->make();
	}
}
