<?php

/**
 * Listen-to attribute.
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
 * `listenTo()`, for use with the `DiscoversListeners` trait — the attribute
 * counterpart to `Listenable::listenTo()`. No event type is given here;
 * `listenTo()` derives it from the method's own first parameter itself, the
 * same way it would for any other callable, and throws the same
 * `InvalidListener` if that parameter isn't a single class or interface type.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ListenTo implements ListenerAttribute
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
		return $registry->listenTo($listener, $this->priority);
	}
}
