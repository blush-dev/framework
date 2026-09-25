<?php

/**
 * URL generation exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use InvalidArgumentException;

/**
 * Thrown when a URL can't be generated: the route name is unknown, a
 * parameter is missing, or a value doesn't satisfy its constraint.
 */
final class UrlGenerationException extends InvalidArgumentException implements RoutingException
{
}
