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
	 * Houses shared data to pass down to subviews.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Collection
	 */
	protected $shared;

	/**
	 * Returns a View object. This should only be used for top-level views
	 * because it resets the shared data when called.  If including views
	 * within views, use `subview()` or descendent functions.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array|string      $names
	 * @param  array|Collection  $data
	 * @return View
	 */
	public function view( $names, $data = [] ) {

		$data['engine'] = $this;

		$view = App::resolve( View::class, compact( 'names', 'data' ) );

		$this->shared = $view->getData();

		return $view;
	}

	/**
	 * Returns a View object. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array|string      $names
	 * @param  array|Collection  $data
	 * @return View
	 */
	public function subview( $names, $data = [] ) {

		if ( $this->shared ) {
			$data = array_merge(
				$this->shared->all(),
				$data instanceof Collection ? $data->all() : $data
			);
		}

		$data['engine'] = $this;

		return App::resolve( View::class, compact( 'names', 'data' ) );
	}

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array|string      $names
	 * @param  array|Collection  $data
	 * @return void
	 */
	public function include( $names, $data = [] ) {
		$this->subview( $names, $data )->display();
	}

	/**
	 * Includes a subview when `$when` is `true`.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  bool              $unless
	 * @param  array|string      $names
	 * @param  array|Collection  $data
	 * @return void
	 */
	public function includeWhen( bool $when, $names, $data = [] ) {
		if ( true === $when ) {
			$this->include( $names, $data );
		}
	}

	/**
	 * Includes a subview unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  bool              $unless
	 * @param  array|string      $names
	 * @param  array|Collection  $data
	 * @return void
	 */
	public function includeUnless( bool $unless, $names, $data = [] ) {
		if ( false === $unless ) {
			$this->include( $names, $data );
		}
	}

	/**
	 * Loops through an array of items and includes a subview for each.  Use
	 * the `$var` variable to set a variable name for the item when passed
	 * to the subview.  Pass a fallback view name via `$empty` to show if
	 * the items array is empty.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array|string $names
	 * @param  array        $items
	 * @param  string       $var
	 * @param  array|string $empty
	 * @return void
	 */
	public function each( $names, array $items = [], string $var = '', $empty = [] ) {

		if ( ! $items && $empty ) {
			$this->include( $empty );
		}

		foreach ( $items as $item ) {
			$this->include(
				$names,
				$var ? [ $var => $item ] : []
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
