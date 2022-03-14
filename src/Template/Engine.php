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

use Blush\App;
use Blush\Template\View;
use Blush\Tools\Collection;

class Engine
{
	/**
	 * Houses shared data to pass down to subviews.
	 *
	 * @since 1.0.0
	 */
	protected Collection $shared;

	/**
	 * Returns a View object. This should only be used for top-level views
	 * because it resets the shared data when called.  If including views
	 * within views, use `subview()` or descendent functions.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function view( $paths, $data = [] ) : View
	{
		$data['engine'] = $this;

		$view = App::resolve( View::class, compact( 'paths', 'data' ) );

		$this->shared = $view->getData();

		return $view;
	}

	/**
	 * Returns a View object. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function subview( $paths, $data = [] ) : View
	{
		if ( $this->shared ) {
			$data = array_merge(
				$this->shared->all(),
				$data instanceof Collection ? $data->all() : $data
			);
		}

		$data['engine'] = $this;

		return App::resolve( View::class, compact( 'paths', 'data' ) );
	}

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function include( $paths, $data = [] ) : void
	{
		$subview = $this->subview( $paths, $data );

		if ( ! $subview->template() ) {
			$templates = array_map(
				fn( $name ) => "`{$name}.php`",
				(array) $paths
			);

			dump( sprintf(
				'Notice: View templates not found: %s.',
				implode( ', ', $templates )
			) );
		}

		$subview->display();
	}

	/**
	 * Includes a subview only if it exists. No errors or warnings if no
	 * view template is found.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeIf( $paths, $data = [] ) : void
	{
		$this->subview( $paths, $data )->display();
	}

	/**
	 * Includes a subview when `$when` is `true`.
	 *
	 * @since  1.0.0
	 * @param  mixed             $when
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeWhen( $when, $paths, $data = [] ) : void
	{
		if ( $when ) {
			$this->include( $paths, $data );
		}
	}

	/**
	 * Includes a subview unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 * @param  mixed             $unless
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeUnless( $unless, $paths, $data = [] ) : void
	{
		if ( ! $unless ) {
			$this->include( $paths, $data );
		}
	}

	/**
	 * Loops through an array of items and includes a subview for each.  Use
	 * the `$var` variable to set a variable name for the item when passed
	 * to the subview.  Pass a fallback view name via `$empty` to show if
	 * the items array is empty.
	 *
	 * @since  1.0.0
	 * @param  array|string $paths
	 * @param  array|string $empty
	 */
	public function each( $paths, iterable $items = [], string $var = '', $empty = [] ) : void
	{
		if ( ! $items && $empty ) {
			$this->include( $empty );
		}

		foreach ( $items as $item ) {
			$this->include(
				$paths,
				$var ? [ $var => $item ] : []
			);
		}
	}

	/**
	 * Outputs a view template.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function display( $paths, $data = [] ) : void
	{
		$this->view( $paths, $data )->display();
	}

	/**
	 * Returns a view template as a string.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function render( $paths, $data = [] ) : string
	{
		return $this->view( $paths, $data )->render();
	}
}
