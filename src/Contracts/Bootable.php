<?php
/**
 * Bootable interface.
 *
 * Defines the contract that bootable classes should utilize. Bootable classes
 * should have a `boot()` method with the singular purpose of "booting" the
 * action and filter hooks for that class. This keeps action/filter calls out of
 * the class constructor. Most bootable classes are meant to be single-instance
 * classes that get loaded once per page request.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts;

/**
 * Bootable interface.
 *
 * @since  5.0.0
 * @access public
 */
interface Bootable {

	/**
	 * Boots the class by running `add_action()` and `add_filter()` calls.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return void
	 */
	public function boot();
}
