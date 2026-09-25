<?php

/**
 * Listener attribute contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener\Attributes;

use Blush\Event\Listener\Listenable;
use Blush\Event\Listener\ListenerId;

/**
 * Contract for an attribute that knows how to register the method it is
 * attached to. Each concrete attribute — `Listen`, `ListenTo`, `ListenOnce`,
 * `ListenToOnce`, `ListenUntil`, `ListenToUntil` — wraps exactly one
 * `Listenable` method and the arguments it takes, so `DiscoversListeners`
 * never has to know which attribute it found on a method; it just calls
 * `registerOn()` and lets the attribute make the matching registry call
 * itself.
 */
interface ListenerAttribute
{
	/**
	 * Registers `$listener` on `$registry` however this attribute's own
	 * `Listenable` method does so, and returns the `ListenerId` that call
	 * returns.
	 */
	public function registerOn(Listenable $registry, callable $listener): ListenerId;
}
