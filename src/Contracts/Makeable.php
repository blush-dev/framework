<?php
/**
 * Makeable interface.
 *
 * Defines the contract that makeable classes should utilize. Makeable classes
 * should have a `make()` method for creating or building all or part of the
 * object and should always return the object itself for chaining methods.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts;

interface Makeable
{
	/**
	 * Makes an object.
	 *
	 * @since 1.0.0
	 */
	public function make(): self;
}
