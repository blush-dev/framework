<?php

/**
 * Event exception contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

use Blush\Core\BlushException;

/**
 * Marker interface implemented by every exception this library throws, so a
 * caller can catch any failure from the event system in one place —
 * `catch (EventException $e)` — without knowing which concrete type was thrown.
 *
 * Each concrete exception also extends the SPL base that best describes its
 * cause (`InvalidArgumentException`, `LogicException`, and so on), so code that
 * catches those broader types keeps working; this interface only adds a
 * library-specific handle on top.
 */
interface EventException extends BlushException
{
}
