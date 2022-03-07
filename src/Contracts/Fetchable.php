<?php
/**
 * Fetchable contract.
 *
 * Fetchable classes should implement a `fetch()` method that returns an HTML
 * string ready for output to the screen. While there's no way to ensure this
 * via the contract, the intent here is for anything that's fetchable to already
 * be escaped. For clarity in the code, when returning raw data, it is
 * recommended to use an alternate method name, such as `get()`, and not use
 * this contract.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts;

/**
 * Fetchable interface.
 *
 * @since  5.0.0
 * @access public
 */
interface Fetchable {

	/**
	 * Returns an HTML string for output.
	 *
	 * @since  5.0.0
	 * @access public
	 * @return string
	 */
	public function fetch();
}
