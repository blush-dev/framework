<?php

/**
 * Listener registry class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

use Closure;
use Override;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use SplObjectStorage;
use Blush\Event\NamedEvent;

/**
 * In-memory, writable listener registry that runs listeners in priority
 * order. This is the one concrete implementation shipped with the library —
 * what you register listeners and subscribers on, and what you hand the
 * dispatcher as a `ListenerProvider`, since it implements the read side
 * (`ListenerProvider`) and the full write side (`Subscribable`, which is
 * itself a `Listenable`) at once.
 *
 * Listeners are registered against an event type — a class or interface name —
 * and run, lowest priority number first, with ties broken by registration order.
 * An event matches a listener when the event's own class, any parent class, or
 * any implemented interface equals the registered type, so a listener on a base
 * type fires for every subtype. A `NamedEvent` additionally matches listeners
 * registered against the string its `eventName()` returns.
 *
 * At dispatch time the matching listeners are sorted, ascending, by priority
 * and then registration order. An `SplObjectStorage` remembers which listeners
 * each subscriber added so `unsubscribe()` can remove them all at once.
 */
final class ListenerRegistry implements ListenerProvider, Subscribable
{
	/**
	 * Stores listeners grouped by event type. Each entry records the
	 * callable listener, its priority, and a monotonic serial used to keep
	 * registration order stable when priorities tie.
	 *
	 * @var array<string, array<int, array{callable: callable, priority: int}>>
	 */
	private array $listeners = [];

	/**
	 * Stores the next serial number, incremented on each registration so
	 * every listener has a unique, ordered identifier.
	 */
	private int $serial = 0;

	/**
	 * Maps each subscriber object to the `ListenerId`s it registered, so
	 * the whole set can be removed on `unsubscribe()`. Populated from
	 * `$recording` while `subscribe()` runs the subscriber's own
	 * `subscribeTo()`.
	 *
	 * @var SplObjectStorage<object, array<int, ListenerId>>
	 */
	private SplObjectStorage $subscribers;

	/**
	 * Collects the ids registered while a `subscribe()` call is in progress,
	 * so `add()` can report a registration happened during a subscriber's
	 * own `subscribeTo()` without that method needing to report anything
	 * back itself. Null when no `subscribe()` call is in progress.
	 * A subscriber only ever receives `Listenable`, not `subscribe()`
	 * itself, so calls never nest and a single frame is always enough.
	 *
	 * @var ?array<int, ListenerId>
	 */
	private ?array $recording = null;

	/**
	 * Sets up the subscriber storage and stores the optional resolver used
	 * to build listeners registered by class name. It receives the class
	 * name and returns the instance.
	 *
	 * @param ?Closure(class-string): object $resolver
	 */
	public function __construct(private readonly ?Closure $resolver = null)
	{
		$this->subscribers = new SplObjectStorage();
	}

	/**
	 * Registers a listener for the given event type. A lower priority number
	 * runs earlier; listeners sharing a priority run in registration order.
	 */
	public function listen(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): ListenerId
	{
		return new ListenerId($eventType, $this->add($eventType, $this->toCallable($listener), $priority));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function listenOnce(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): ListenerId
	{
		return $this->listenUntil($eventType, $listener, self::always(), $priority);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function listenUntil(string $eventType, callable|string $listener, callable $until, int|ListenerPriority $priority = 0): ListenerId
	{
		return new ListenerId($eventType, $this->add(
			$eventType,
			$this->untilListener($eventType, $this->toCallable($listener), $until),
			$priority
		));
	}

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	#[Override]
	public function listenTo(callable $listener, int|ListenerPriority $priority = 0): ListenerId
	{
		$eventType = $this->deriveEventType($listener);

		return new ListenerId($eventType, $this->add($eventType, $listener, $priority));
	}

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	#[Override]
	public function listenToOnce(callable $listener, int|ListenerPriority $priority = 0): ListenerId
	{
		return $this->listenToUntil($listener, self::always(), $priority);
	}

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	#[Override]
	public function listenToUntil(callable $listener, callable $until, int|ListenerPriority $priority = 0): ListenerId
	{
		$eventType = $this->deriveEventType($listener);

		return new ListenerId($eventType, $this->add(
			$eventType,
			$this->untilListener($eventType, $listener, $until),
			$priority
		));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forget(string $eventType, ?callable $listener = null): void
	{
		if (! isset($this->listeners[$eventType])) {
			return;
		}

		if ($listener === null) {
			unset($this->listeners[$eventType]);
			return;
		}

		foreach ($this->listeners[$eventType] as $serial => $registered) {
			if ($registered['callable'] === $listener) {
				unset($this->listeners[$eventType][$serial]);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forgetId(ListenerId $id): void
	{
		unset($this->listeners[$id->eventType][$id->serial]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function subscribe(ListenerSubscriber $subscriber): void
	{
		$this->recording = [];

		try {
			$subscriber->subscribeTo($this);

			$this->subscribers[$subscriber] = $this->recording ?? [];
		} finally {
			$this->recording = null;
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function unsubscribe(ListenerSubscriber $subscriber): void
	{
		if (! isset($this->subscribers[$subscriber])) {
			return;
		}

		foreach ($this->subscribers[$subscriber] as $id) {
			$this->forgetId($id);
		}

		unset($this->subscribers[$subscriber]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getListenersForEvent(object $event): iterable
	{
		$matched = [];

		foreach ($this->matchingTypes($event) as $type) {
			foreach ($this->listeners[$type] ?? [] as $serial => $registered) {
				$matched[] = [
					'priority' => $registered['priority'],
					'serial'   => $serial,
					'callable' => $registered['callable']
				];
			}
		}

		// Lowest priority number first, ties broken by registration
		// order (the serial). Both sort ascending.
		usort($matched, static fn (array $a, array $b): int =>
			[$a['priority'], $a['serial']] <=> [$b['priority'], $b['serial']]);

		foreach ($matched as $entry) {
			yield $entry['callable'];
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hasListeners(string $eventType): bool
	{
		foreach ($this->typesForClass($eventType) as $type) {
			if (! empty($this->listeners[$type])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes a registered listener to a callable. A callable is
	 * returned as-is; a class name is turned into a closure that resolves
	 * the class the first time it runs (lazily, then cached) and invokes it
	 * — so an invokable class is only built if its event actually fires.
	 * Any other string is rejected.
	 */
	private function toCallable(callable|string $listener): callable
	{
		if (is_string($listener) && class_exists($listener)) {
			$instance = null;

			return function (object $event) use ($listener, &$instance): void {
				$instance ??= $this->resolveListener($listener);
				$instance($event);
			};
		}

		if (! is_callable($listener)) {
			throw new InvalidListener(
				'Listener must be a callable or the class name of an invokable class.'
			);
		}

		return $listener;
	}

	/**
	 * Derives the event type a listener handles from the declared type of
	 * its first parameter, so `listenTo()` callers need not repeat the
	 * class they already type-hinted. `Closure::fromCallable()` normalizes
	 * every callable shape — closure, `[$object, 'method']`, function name,
	 * invokable object, first-class callable — into one thing to reflect.
	 * The parameter must declare a single class or interface: a missing,
	 * builtin, union, or intersection type names no event to register
	 * against, so it is rejected rather than guessed at.
	 *
	 * @throws ReflectionException
	 */
	private function deriveEventType(callable $listener): string
	{
		$parameters = (new ReflectionFunction($listener(...)))->getParameters();
		$type = ($parameters[0] ?? null)?->getType();

		if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
			throw new InvalidListener(
				'Cannot derive the event type from the listener: its first parameter must declare a single class or interface type. Register it with listen($eventType, $listener) instead.'
			);
		}

		return $type->getName();
	}

	/**
	 * Builds a listener registered by class name and returns it, ensuring
	 * the result is invokable. Uses the resolver when one was given,
	 * otherwise `new $class()`.
	 *
	 * @param class-string $class
	 */
	private function resolveListener(string $class): callable
	{
		$listener = $this->resolver ? ($this->resolver)($class) : new $class();

		if (! is_callable($listener)) {
			throw new NotInvokable(sprintf(
				'Listener class "%s" must be invokable; define an __invoke() method.',
				$class
			));
		}

		return $listener;
	}

	/**
	 * Wraps a listener so it removes itself once `$until` says to stop —
	 * used by `listenUntil()` and `listenToUntil()`, the only callers.
	 * `listenOnce()`/`listenToOnce()` don't call this directly; they delegate
	 * to `listenUntil()`/`listenToUntil()` with `self::always()`, which stops
	 * after the very first delivery. `$until` is checked against the event
	 * about to be delivered *before* the listener runs, and removal happens
	 * before that call too, so a listener never fires more times than
	 * `$until` allows, even if it (or something it calls) dispatches the same
	 * event again, and even if it throws. The `&$wrapped` reference lets the
	 * closure forget the exact callable stored.
	 */
	private function untilListener(string $eventType, callable $listener, callable $until): callable
	{
		// Assigned and returned in a single statement so the closure
		// can still capture itself by reference (`&$wrapped`) — it
		// needs its own identity to forget the exact callable stored.
		return $wrapped = function (object $event) use (&$wrapped, $eventType, $listener, $until): void {
			if ($until($event)) {
				$this->forget($eventType, $wrapped);
			}

			$listener($event);
		};
	}

	/**
	 * Returns the `$until` callable `listenOnce()` and `listenToOnce()` pass
	 * to `listenUntil()` and `listenToUntil()`: one that always says to stop,
	 * regardless of the event, so the listener never survives past its first
	 * delivery.
	 */
	private static function always(): callable
	{
		return static fn (): bool => true;
	}

	/**
	 * Stores a listener and returns the serial assigned to it. A named
	 * `ListenerPriority` is resolved to its integer value here, so every
	 * caller can accept either form.
	 *
	 * When called while a `subscribe()` is in progress, also appends the
	 * new id to `$recording` — the only place a registration made during a
	 * subscriber's own `subscribeTo()` is noticed.
	 */
	private function add(string $eventType, callable $listener, int|ListenerPriority $priority): int
	{
		$serial = $this->serial++;

		$this->listeners[$eventType][$serial] = [
			'callable' => $listener,
			'priority' => $priority instanceof ListenerPriority ? $priority->toInt() : $priority
		];

		if ($this->recording !== null) {
			$this->recording[] = new ListenerId($eventType, $serial);
		}

		return $serial;
	}

	/**
	 * Returns every key a listener may be registered against to match the
	 * given event: its class hierarchy (via `typesForClass()`) plus, for a
	 * `NamedEvent`, its event name.
	 *
	 * @return array<int, string>
	 */
	private function matchingTypes(object $event): array
	{
		$types = $this->typesForClass($event::class);

		// A named event contributes its name as an extra key, so
		// listeners registered against that string match alongside the
		// class-based ones.
		if ($event instanceof NamedEvent) {
			$types[] = $event->eventName();
		}

		return $types;
	}

	/**
	 * Returns the keys a listener may be registered against to match the
	 * given type: the class itself plus every parent class and implemented
	 * interface. A string that is not a loaded class or interface (such as
	 * a named event's name) is treated as an opaque key with no hierarchy.
	 *
	 * @return array<int, string>
	 */
	private function typesForClass(string $class): array
	{
		if (! class_exists($class) && ! interface_exists($class)) {
			return [$class];
		}

		return [
			$class,
			...array_values(class_parents($class) ?: []),
			...array_values(class_implements($class) ?: [])
		];
	}
}
