<?php

/**
 * Invalid listener exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

use InvalidArgumentException;
use Blush\Event\EventException;

/**
 * Thrown when a value registered as a listener is neither a callable nor the
 * class name of an invokable class. It extends `InvalidArgumentException`
 * because it reports a bad argument handed to the registry at registration time.
 */
final class InvalidListener extends InvalidArgumentException implements EventException
{
}
