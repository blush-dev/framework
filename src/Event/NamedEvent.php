<?php

/**
 * Named event contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

/**
 * Contract for an event that exposes a string name in addition to its class.
 * Listeners are normally matched by the event's type, but a named event also
 * matches listeners registered against the string its `eventName()` returns, so
 * you can register against a friendly identifier as well as (or instead of) the
 * class. The event is still dispatched as an object — the name is an extra
 * routing key it opts into, not a replacement for the typed event.
 *
 * Pair this with the `Named` trait to implement `eventName()` from a `NAME`
 * class constant, which keeps the name greppable and refactor-safe (register
 * with `MyEvent::NAME` rather than a bare string).
 */
interface NamedEvent
{
	/**
	 * Returns the event's name, used as an additional key for matching
	 * listeners registered against that string.
	 */
	public function eventName(): string;
}
