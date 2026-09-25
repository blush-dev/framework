<?php

/**
 * Unbootable service exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * Thrown when a service listed in a provider's `BOOTABLE` constant does not
 * implement the `Bootable` contract. Extends `ApplicationException`, so catching
 * the latter also catches unbootable-service failures.
 */
final class UnbootableServiceException extends ApplicationException
{
}
