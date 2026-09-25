<?php

/**
 * Listener subscriber contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

/**
 * Contract for an object that registers several listeners at once. Rather than
 * wiring each listener individually, a subscriber registers its own listeners
 * on the registry it is given, and the registry's `subscribe()` remembers what
 * it registered so the whole set can be removed together with `unsubscribe()`.
 */
interface ListenerSubscriber
{
	/**
	 * Registers this subscriber's listeners on the given registry, which
	 * are typically a handful of `listen()`, `listenTo()`, `listenOnce()`,
	 * or `listenToOnce()` calls, mixed freely as each listener needs.
	 * Called once per `subscribe()` call; everything registered here is
	 * tracked together, so `unsubscribe()` can remove it all in one call.
	 */
	public function subscribeTo(Listenable $registry): void;
}
