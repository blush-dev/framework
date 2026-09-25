<?php

/**
 * Invalid route.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use LogicException;

/**
 * Thrown when a route or redirect is badly defined: a malformed pattern, a
 * handler that doesn't exist, or a name used twice. These surface when the
 * route table is compiled, so they show up on the first request in
 * development and in `cache:compile` on deploy.
 */
final class InvalidRoute extends LogicException implements RoutingException
{
}
