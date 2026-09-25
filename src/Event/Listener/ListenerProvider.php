<?php

/**
 * Listener provider contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

use Override;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Contract for the object that maps an event to the listeners interested in it.
 * A listener is any callable that accepts the event as its only argument; the
 * provider decides which listeners apply to a given event and the order in
 * which they run.
 */
interface ListenerProvider extends ListenerProviderInterface
{
	/**
	 * Returns an iterable of callable listeners for the given event, in the
	 * order they should be called.
	 *
	 * @return iterable<callable>
	 */
	#[Override]
	public function getListenersForEvent(object $event): iterable;
}
