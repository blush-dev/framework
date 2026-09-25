<?php

/**
 * Listen-once attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener\Attributes;

use Attribute;
use Override;
use Blush\Event\Listener\Listenable;
use Blush\Event\Listener\ListenerId;
use Blush\Event\Listener\ListenerPriority;

/**
 * Marks a `ListenerSubscriber` method as a listener registered with
 * `listenOnce()`, for use with the `DiscoversListeners` trait — the
 * attribute counterpart to `Listenable::listenOnce()`, taking the same
 * event type and priority. The method removes itself after it first runs.
 *
 * Repeatable, so the same method can be registered under more than one event
 * type or key, exactly as `Listen` is.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ListenOnce implements ListenerAttribute
{
	public function __construct(
		public readonly string $eventType,
		public readonly int|ListenerPriority $priority = 0
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function registerOn(Listenable $registry, callable $listener): ListenerId
	{
		return $registry->listenOnce($this->eventType, $listener, $this->priority);
	}
}
