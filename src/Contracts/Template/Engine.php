<?php
/**
 * Template engine interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Template;

interface Engine
{
	/**
	 * Returns a View object. This should only be used for top-level views
	 * because it resets the shared data when called.  If including views
	 * within views, use `subview()` or descendent functions.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function view( $paths, $data = [] ): View;

	/**
	 * Returns a View object. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function subview( $paths, $data = [] ): View;

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function include( $paths, $data = [] ): void;

	/**
	 * Includes a subview only if it exists. No errors or warnings if no
	 * view template is found.
	 *
	 * @since  1.0.0
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeIf( $paths, $data = [] ): void;

	/**
	 * Includes a subview when `$when` is `true`.
	 *
	 * @since  1.0.0
	 * @param  mixed             $when
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeWhen( $when, $paths, $data = [] ): void;

	/**
	 * Includes a subview unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 * @param  mixed             $unless
	 * @param  array|string      $paths
	 * @param  array|Collection  $data
	 */
	public function includeUnless( $unless, $paths, $data = [] ): void;

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
	public function each(
		$paths,
		iterable $items = [],
		string $var = '',
		$empty = []
	): void;
}
