<?php

/**
 * Container exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Blush\Core\BlushException;

/**
 * Base exception for any container failure, such as a binding that cannot be
 * built or an unresolvable constructor dependency. Catch this to handle any
 * container-originated error.
 */
class ContainerException extends Exception implements BlushException, ContainerExceptionInterface
{
}
