<?php

/**
 * Listen-until attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener\Attributes;

use Attribute;
use Closure;
use Override;
use Blush\Event\Listener\Listenable;
use Blush\Event\Listener\ListenerId;
use Blush\Event\Listener\ListenerPriority;

/**
 * Marks a `ListenerSubscriber` method as a listener registered with
 * `listenUntil()`, for use with the `DiscoversListeners` trait — the
 * attribute counterpart to `Listenable::listenUntil()`, taking the same
 * event type, stop condition, and priority.
 *
 * `$until` is a closure, which PHP 8.5 allows as an attribute argument.
 *
 * Repeatable, so the same method can be registered under more than one event
 * type or key, exactly as `Listen` is.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ListenUntil implements ListenerAttribute
{
	public function __construct(
		public readonly string $eventType,
		public readonly Closure $until,
		public readonly int|ListenerPriority $priority = 0
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function registerOn(Listenable $registry, callable $listener): ListenerId
	{
		return $registry->listenUntil($this->eventType, $listener, $this->until, $this->priority);
	}
}
