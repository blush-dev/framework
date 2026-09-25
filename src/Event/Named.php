<?php

/**
 * Named event trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event;

/**
 * Ready-made `NamedEvent` implementation that reads the name from a `NAME`
 * class constant. A using class defines the constant and the name comes for
 * free:
 *
 *     final class OrderPlaced implements NamedEvent
 *     {
 *         use Named;
 *         public const NAME = 'order.placed';
 *     }
 *
 * Keeping the name in a constant means listeners register with `OrderPlaced::NAME`
 * instead of a bare string, so it stays greppable and survives renaming.
 */
trait Named
{
	/**
	 * Returns the name declared in the using class's `NAME` constant.
	 */
	public function eventName(): string
	{
		return static::NAME;
	}
}
