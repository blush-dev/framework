<?php
/**
 * Renderable contract.
 *
 * Renderable classes should implement a `render()` method that returns an HTML
 * string ready for output to the screen. While there's no way to ensure this
 * via the contract, the intent here is for anything that's renderable to already
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

interface CastsToHtml
{
	/**
	 * Returns an HTML string for output.
	 *
	 * @since 1.0.0
	 */
	public function toHtml(): string;
}
