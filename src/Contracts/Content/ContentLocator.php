<?php
/**
 * Content locator interface.
 *
 * Defines the contract that content locator classes should implement.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Content;

interface ContentLocator
{
	/**
	 * Sets the locator path.
	 *
	 * @since 1.0.0
	 */
	public function setPath( string $path ): void;

	/**
	 * Returns the folder path relative to the content directory.
	 *
	 * @since 1.0.0
	 */
	public function path(): string;

	/**
	 * Returns collection of located files as an array. The filenames are
	 * the array keys and the metadata is the value.
	 *
	 * @since 1.0.0
	 */
	public function all(): array;
}
