<?php

/**
 * Dispatcher contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Contract for the object that dispatches an event to its listeners. An event
 * is any object; the dispatcher hands it to each registered listener in turn
 * and returns the same instance, which listeners may have mutated.
 */
interface Dispatcher extends EventDispatcherInterface
{
	/**
	 * Provides the given event to all relevant listeners and returns it. If
	 * the event implements `StoppableEvent`, dispatch stops as soon as
	 * propagation has been stopped.
	 *
	 * The same event instance is always returned, so the concrete event type
	 * is preserved for static analysis: `dispatch(new Foo())` is typed `Foo`.
	 *
	 * @template TEvent of object
	 * @param    TEvent $event
	 * @return   TEvent
	 */
	#[Override]
	public function dispatch(object $event): object;
}
