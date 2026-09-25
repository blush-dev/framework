<?php

/**
 * Invalid provider exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * Thrown when a value registered with the application is not a `ServiceProvider`
 * class or instance. Extends `ApplicationException`, so catching the latter also
 * catches invalid-provider failures.
 */
final class InvalidProviderException extends ApplicationException
{
}
