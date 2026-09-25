<?php

/**
 * Listen-to-until attribute.
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
 * `listenToUntil()`, for use with the `DiscoversListeners` trait — the
 * attribute counterpart to `Listenable::listenToUntil()`. No event type is
 * given here; `listenToUntil()` derives it from the method's own first
 * parameter itself, exactly as `ListenTo` does.
 *
 * `$until` is a closure, which PHP 8.5 allows as an attribute argument.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ListenToUntil implements ListenerAttribute
{
	public function __construct(
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
		return $registry->listenToUntil($listener, $this->until, $this->priority);
	}
}
