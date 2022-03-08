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

	protected $shared;

	/**
	 * Returns a View object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string            $names
	 * @param  array|Collection  $data
	 * @return View
	 */
	public function view( $names, $data = [] ) {

		$data['engine'] = $this;

		$view = App::resolve( View::class, compact( 'names', 'data' ) );

		$this->shared = $view->getData();

		return $view;
	}

	public function include( $names, $data = [] ) {

		if ( $this->shared ) {
			$data = array_merge(
				$this->shared->all(),
				$data instanceof Collection ? $data->all() : $data
			);
		}

		$data['engine'] = $this;

		return App::resolve( View::class, compact( 'names', 'data' ) )->display();
	}

	public function includeWhen( bool $when, $names, $data = [] ) {
		if ( true === $when ) {
			$this->include( $names, $data );
		}
	}

	public function includeUnless( bool $unless, $names, $data = [] ) {
		if ( false === $unless ) {
			$this->include( $names, $data );
		}
	}


	public function each( $names, array $items = [], string $data_slug = '' ) {
		foreach ( $items as $item ) {
			$view = $this->include(
				$names,
				$data_slug ? [ $data_slug => $item ] : []
			);
		}
	}

	/**
	 * Outputs a view template.
	 *
	 * @since  1.00
	 * @access public
	 * @param  string            $names
	 * @param  array|Collection  $data
	 * @return void
	 */
	public function display( $names, $data = [] ) {
		$this->view( $names, $data )->display();
	}

	/**
	 * Returns a view template as a string.
	 *
	 * @since  1.00
	 * @access public
	 * @param  string            $names
	 * @param  array|Collection  $data
	 * @return string
	 */
	public function render( $names, $data = [] ) {
		return $this->view( $names, $data )->render();
	}
}
