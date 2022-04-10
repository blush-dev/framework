<?php
/**
 * URL interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Routing;

interface Url
{
	/**
	 * Return the app URL and append an optional path.
	 *
	 * @since 1.0.0
	 */
	public function to( string $append = '' ): string;

	/**
	 * Returns a route's URL.
	 *
	 * @since 1.0.0
	 */
	public function route( string $name, array $params = [] ): string;

	/**
	 * Accepts a path or URL string with possible `{param}` values in it.
	 * Replaces the `{param}` strings with values from the `$params` array.
	 *
	 * @since 1.0.0
	 */
	public function parseParams( string $path, array $params = [] ): string;
}
