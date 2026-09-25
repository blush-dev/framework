<?php

/**
 * Listen-to-once attribute.
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
 * `listenToOnce()`, for use with the `DiscoversListeners` trait — the
 * attribute counterpart to `Listenable::listenToOnce()`. No event type is
 * given here; `listenToOnce()` derives it from the method's own first
 * parameter itself, exactly as `ListenTo` does, and the method removes
 * itself after it first runs.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ListenToOnce implements ListenerAttribute
{
	public function __construct(
		public readonly int|ListenerPriority $priority = 0
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function registerOn(Listenable $registry, callable $listener): ListenerId
	{
		return $registry->listenToOnce($listener, $this->priority);
	}
}
