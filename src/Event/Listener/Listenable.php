<?php

/**
 * Listenable contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

/**
 * Contract for the write side of the listener system: registering listeners,
 * removing them again, and reporting what is registered. It's the
 * counterpart to the read-only `ListenerProvider`, which only answers which
 * listeners apply to an event. A provider may implement both to be the
 * single place listeners are stored, or just `ListenerProvider` when it
 * sources listeners from elsewhere and accepts no registrations.
 *
 * This is what a `ListenerSubscriber` and a `ListenerAttribute` are handed —
 * enough to add, remove, and inspect listeners, but nothing about
 * subscribers. `Subscribable` extends this with `subscribe()`/`unsubscribe()`
 * for the object that manages subscriber bookkeeping; a plain `Listenable`
 * is already a complete registry without it.
 */
interface Listenable
{
	/**
	 * Registers a listener for the given event type. The listener may be
	 * any callable, or the class name of an invokable class to resolve
	 * lazily when the event first fires. A lower priority number runs
	 * earlier; listeners sharing a priority run in registration order. The
	 * priority may be a plain integer or a `ListenerPriority` case.
	 *
	 * Returns a `ListenerId` handle to this exact registration; pass it to
	 * `forgetId()` to remove the listener without holding the callable.
	 */
	public function listen(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Registers a listener that runs at most once: it removes itself before
	 * it is called, so it fires for the first matching event and never
	 * again. In every other respect it behaves like `listen()`, including
	 * returning a `ListenerId` handle — useful to cancel it before it fires.
	 */
	public function listenOnce(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Registers a listener that removes itself once `$until` says to stop.
	 * Before each delivery, `$until` is called with the event about to be
	 * delivered; if it returns `true`, the listener is removed *before*
	 * that delivery runs — so this is its last call — and it is otherwise
	 * identical to `listen()`. `listenOnce()` is the special case where
	 * `$until` always returns `true`.
	 *
	 * Because removal happens once per registration, not once per subject
	 * inside the event stream, `$until` should track state that is global
	 * to this listener (a count, a flag, a specific qualifying event) rather
	 * than per-entity state (such as an id on the event) — the first event
	 * that satisfies `$until` stops the listener for every future event of
	 * this type, not just events sharing that entity.
	 */
	public function listenUntil(string $eventType, callable|string $listener, callable $until, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Registers a listener, deriving the event type from the declared type
	 * of its first parameter rather than taking it as an argument — so the
	 * class you already type-hinted is not repeated. Otherwise, identical
	 * to `listen()`. Use `listen()` when there is no type to read: a
	 * listener class name, a `NamedEvent` name, or an untyped parameter.
	 * The first parameter must declare a single class or interface type;
	 * anything else throws, since no event type can be derived from it.
	 * Returns a `ListenerId` handle, as `listen()` does.
	 */
	public function listenTo(callable $listener, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Registers a once-only listener, deriving the event type from its
	 * first parameter as `listenTo()` does. It combines the two: fires at
	 * most once, and takes no event-type argument. The same derivation
	 * rules and restrictions apply, and it returns a `ListenerId` handle.
	 */
	public function listenToOnce(callable $listener, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Registers an until-listener, deriving the event type from its first
	 * parameter as `listenTo()` does. It combines the two: removes itself
	 * once `$until` says to stop, and takes no event-type argument. The same
	 * derivation rules and restrictions apply, and it returns a `ListenerId`
	 * handle.
	 */
	public function listenToUntil(callable $listener, callable $until, int|ListenerPriority $priority = 0): ListenerId;

	/**
	 * Removes listeners registered for the given event type. When a listener
	 * is given, only listeners equal to it (by identity) are removed; when
	 * it is omitted, every listener for the type is removed. Listeners added
	 * as an inline closure can only be removed by passing back the same
	 * closure instance — or by handle with `forgetId()`.
	 */
	public function forget(string $eventType, ?callable $listener = null): void;

	/**
	 * Removes the single listener a `ListenerId` refers to — the handle
	 * returned by `listen()` and its variants. Unlike `forget()`, it needs
	 * neither the event type nor the callable, so an inline closure can be
	 * revoked without keeping a reference. An unknown or already-removed
	 * handle does nothing.
	 */
	public function forgetId(ListenerId $id): void;

	/**
	 * Reports whether any registered listener would match the given event
	 * type — its own class or interface, or any parent class or implemented
	 * interface. Handy for skipping work, such as building an expensive
	 * event, when nothing is listening. Pass a named event's name to check
	 * listeners registered under that name. This reflects only listeners
	 * registered here.
	 */
	public function hasListeners(string $eventType): bool;
}
