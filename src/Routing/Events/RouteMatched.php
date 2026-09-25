<?php

/**
 * Route matched event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Events;

use Psr\Http\Message\ServerRequestInterface;
use Blush\Routing\RouteMatch;

/**
 * Dispatched by the router once a route matches, before the route's
 * middleware and handler run. The request already carries the match and
 * its parameters as attributes.
 */
final readonly class RouteMatched
{
	public function __construct(
		public ServerRequestInterface $request,
		public RouteMatch $match
	) {}
}
