<?php
/**
 * Template engine.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template;

use Blush\Proxies\App;
use Blush\Template\View;
use Blush\Tools\Collection;

class Engine {

	/**
	 * Returns a View object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string            $name
	 * @param  array|string      $slugs
	 * @param  array|Collection  $data
	 * @return View
	 */
	public function view( string $name, array $slugs = [], $data = [] ) {

		if ( ! $data instanceof Collection ) {
			$data = new Collection( (array) $data );
		}

		// Pass the engine itself along so that it can be used directly
		// in views.
		$data->add( 'engine', $this );

		return App::resolve( View::class, compact( 'name', 'slugs', 'data' ) );
	}

	/**
	 * Outputs a view template.
	 *
	 * @since  1.00
	 * @access public
	 * @param  string            $name
	 * @param  array|string      $slugs
	 * @param  array|Collection  $data
	 * @return void
	 */
	public function display( string $name, array $slugs = [], $data = [] ) {
		$this->view( $name, $slugs, $data )->display();
	}

	/**
	 * Returns a view template as a string.
	 *
	 * @since  1.00
	 * @access public
	 * @param  string            $name
	 * @param  array|string      $slugs
	 * @param  array|Collection  $data
	 * @return string
	 */
	public function render( string $name, array $slugs = [], $data = [] ) {
		return $this->view( $name, $slugs, $data )->render();
	}
}
