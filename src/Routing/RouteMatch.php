<?php

/**
 * Route match.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * A matched route and the parameter values taken from the path
 * (percent-decoded, still strings). The router adds it to the request as
 * the `RouteMatch::class` attribute, and each parameter as its own
 * attribute.
 */
final readonly class RouteMatch
{
	/**
	 * @param array<string, string> $params
	 */
	public function __construct(
		public CompiledRoute $route,
		public array $params = []
	) {}
}
