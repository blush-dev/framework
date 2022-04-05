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

use Blush\Tools\Collection;

interface Engine
{
	/**
	 * Returns a View object. This should only be used for top-level views
	 * because it resets the shared data when called.  If including views
	 * within views, use `subview()` or descendent functions.
	 *
	 * @since  1.0.0
	 */
	public function view( array|string $paths, array|Collection $data = [] ): View;

	/**
	 * Returns a View object. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 */
	public function subview( array|string $paths, array|Collection $data = [] ): View;

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 */
	public function include( array|string $paths, array|Collection $data = [] ): void;

	/**
	 * Includes a subview only if it exists. No errors or warnings if no
	 * view template is found.
	 *
	 * @since  1.0.0
	 */
	public function includeIf( array|string $paths, array|Collection $data = [] ): void;

	/**
	 * Includes a subview when `$when` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeWhen(
		mixed $when,
		array|string $paths,
		array|Collection $data = []
	): void;

	/**
	 * Includes a subview unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeUnless(
		mixed $unless,
		array|string $paths,
		array|Collection $data = []
	): void;

	/**
	 * Loops through an array of items and includes a subview for each.  Use
	 * the `$var` variable to set a variable name for the item when passed
	 * to the subview.  Pass a fallback view name via `$empty` to show if
	 * the items array is empty.
	 *
	 * @since  1.0.0
	 */
	public function each(
		array|string $paths,
		iterable $items = [],
		string $var = '',
		array|string $empty = []
	): void;
}
