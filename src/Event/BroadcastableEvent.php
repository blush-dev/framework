<?php

/**
 * Broadcastable event contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

/**
 * Contract for an event that can push itself out beyond the dispatcher's
 * typed listeners after being dispatched. `dispatch()` only ever calls
 * listeners registered on the provider; a `BroadcastableEvent` additionally
 * knows how to make itself visible somewhere else: the log, a queue for async
 * work, or a webhook out to another service. Nothing about the contract ties
 * it to one target.
 */
interface BroadcastableEvent
{
	/**
	 * Broadcasts the event beyond the dispatcher's typed listeners and
	 * returns the same instance, so the call chains onto `dispatch()`:
	 *
	 *     $event = $dispatcher->dispatch(new OrderPlaced($order))->broadcast();
	 */
	public function broadcast(): static;
}
