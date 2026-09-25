<?php

/**
 * Stoppable event contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

use Override;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Contract for an event whose propagation can be stopped. When the dispatcher
 * sees that propagation has been stopped, it calls no further listeners for that
 * event. The companion `Stoppable` trait provides a ready-made implementation.
 */
interface StoppableEvent extends StoppableEventInterface
{
	/**
	 * Determines whether the previous listener halted propagation, in which
	 * case the dispatcher must not call any further listeners.
	 */
	#[Override]
	public function isPropagationStopped(): bool;
}
