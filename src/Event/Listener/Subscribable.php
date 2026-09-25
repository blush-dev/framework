<?php

/**
 * Subscribable contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

/**
 * Extends `Listenable` with subscriber management — accepting a
 * `ListenerSubscriber`, remembering everything it registered, and removing
 * that whole set again in one call. Not every registry needs this: a
 * `Listenable` on its own is already a complete add/remove/inspect store.
 * Implement this on top only when subscribers are actually in play — and
 * because `subscribe()` hands the subscriber `$this` as a `Listenable`,
 * extending it here is what makes that call type-check at all.
 */
interface Subscribable extends Listenable
{
	/**
	 * Lets the subscriber register its own listeners on this registry —
	 * typically a handful of `listen()`, `listenTo()`, `listenOnce()`, or
	 * `listenToOnce()` calls — and remembers what it registered, so the
	 * whole set can be removed together with `unsubscribe()`.
	 */
	public function subscribe(ListenerSubscriber $subscriber): void;

	/**
	 * Removes every listener previously registered by the given subscriber.
	 */
	public function unsubscribe(ListenerSubscriber $subscriber): void;
}
