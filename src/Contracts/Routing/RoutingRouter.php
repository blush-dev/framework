<?php
/**
 * Router interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Routing;

use Symfony\Component\HttpFoundation\{Request, Response};

interface RoutingRouter
{
	/**
	 * Returns the HTTP request.
	 *
	 * @since 1.0.0
	 */
	public function request(): Request;

	/**
	 * Returns the request path.
	 *
	 * @since 1.0.0
	 */
	public function path(): string;

	/**
	 * Returns a cached HTTP Response if global caching is enabled.  If not,
	 * returns a new HTTP Response.
	 *
	 * @since 1.0.0
	 */
	public function response(): Response;
}
