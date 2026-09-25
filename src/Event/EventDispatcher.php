<?php

/**
 * Event dispatcher class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

use Override;
use Blush\Event\Listener\ListenerProvider;

/**
 * Dispatches events synchronously, in process, in the calling request. It asks
 * the listener provider for the listeners interested in an event and calls each
 * one in turn, passing the event. When the event is stoppable, it stops calling
 * listeners the moment propagation has been stopped. The same event instance is
 * returned so callers can read whatever the listeners changed on it.
 *
 * Dispatching is its only job: listeners are registered on the provider (a
 * `Listenable` such as `ListenerRegistry`), not on the dispatcher.
 */
final class EventDispatcher implements Dispatcher
{
	/**
	 * Stores the listener provider that supplies listeners for each event.
	 */
	public function __construct(
		private readonly ListenerProvider $listeners
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function dispatch(object $event): object
	{
		$stoppable = $event instanceof StoppableEvent;

		foreach ($this->listeners->getListenersForEvent($event) as $listener) {
			if ($stoppable && $event->isPropagationStopped()) {
				break;
			}

			$listener($event);
		}

		return $event;
	}
}
