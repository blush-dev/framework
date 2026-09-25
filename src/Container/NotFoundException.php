<?php

/**
 * Not found exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when the container has no entry for the requested identifier and is
 * unable to resolve it. Extends `ContainerException`, so catching the latter
 * also catches not-found failures.
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
