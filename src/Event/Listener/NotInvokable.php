<?php

/**
 * Non-invokable listener exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

use LogicException;
use Blush\Event\EventException;

/**
 * Thrown when a listener registered by class name resolves to an object that
 * cannot be called — a listener class must define `__invoke()`. It extends
 * `LogicException` because a non-invokable listener class is a programming
 * error in the wiring, not a runtime input problem.
 */
final class NotInvokable extends LogicException implements EventException
{
}
