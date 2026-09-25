<?php

/**
 * Stoppable trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

/**
 * Drop-in implementation of `StoppableEvent` for event classes. A listener
 * calls `stopPropagation()` to signal that no further listeners should run for
 * the event. Use this trait and implement the interface on the event:
 *
 *     final class ExampleEvent implements StoppableEvent
 *     {
 *             use Stoppable;
 *     }
 */
trait Stoppable
{
	/**
	 * Whether a listener has stopped propagation of the event.
	 */
	private bool $propagationStopped = false;

	/**
	 * Stops propagation so the dispatcher calls no further listeners.
	 */
	public function stopPropagation(): void
	{
		$this->propagationStopped = true;
	}

	/**
	 * Determines whether a listener has stopped propagation, in which case the
	 * dispatcher must not call any further listeners.
	 */
	public function isPropagationStopped(): bool
	{
		return $this->propagationStopped;
	}
}
