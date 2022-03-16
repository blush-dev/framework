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

// Abstracts.
use Blush\Contracts\Template\Engine as EngineContract;
use Blush\Contracts\Template\View;

// Concretes.
use Blush\App;
use Blush\Tools\Collection;

class Engine implements EngineContract
{
	/**
	 * Houses shared data to pass down to subviews.
	 *
	 * @since 1.0.0
	 */
	protected Collection $shared;

	/**
	 * Whether the top-level page view has been booted.
	 *
	 * @since 1.0.0
	 */
	protected bool $view_booted = false;

	/**
	 * Returns a View object. This should only be used for top-level views.
	 * Otherwise, an error message is dumped and the process is stalled.
	 * If including views within views, use `subview()` or one of its
	 * several descendent methods included in this class.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function view( $paths, $data = [] ): View
	{
		// If `view()` is called for a second time on a single page load
		// dump and die.
		if ( $this->view_booted ) {
			dump( 'Cannot call Engine::view() twice. If this is a sub-view, try the Engine::subview() method.' );
			die();
		}

		// Always pass the engine back to the view.
		$data['engine'] = $this;

		// Make a new `View`.
		$view = App::make( View::class, compact( 'paths', 'data' ) );

		// Set object properties.
		$this->shared      = $view->getData();
		$this->view_booted = true;

		// Returns a `View` object.
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
	public function subview( $paths, $data = [] ): View
	{
		if ( $this->shared ) {
			$data = array_merge(
				$this->shared->all(),
				$data instanceof Collection ? $data->all() : $data
			);
		}

		$data['engine'] = $this;

		return App::make( View::class, compact( 'paths', 'data' ) );
	}

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function include( $paths, $data = [] ): void
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
	public function includeIf( $paths, $data = [] ): void
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
	public function includeWhen( $when, $paths, $data = [] ): void
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
	public function includeUnless( $unless, $paths, $data = [] ): void
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
	public function each( $paths, iterable $items = [], string $var = '', $empty = [] ): void
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
}
