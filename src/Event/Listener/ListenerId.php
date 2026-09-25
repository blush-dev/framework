<?php

/**
 * Listener identifier value object.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

/**
 * Opaque handle to a single registered listener. The registry's `listen()`
 * family returns one, and `forgetId()` accepts it to revoke that exact
 * registration — so an inline closure can be removed without keeping a
 * reference to the closure itself.
 *
 * It is a token, not data: hold it, pass it back, or compare it by identity.
 * The fields are the registry's lookup coordinates, exposed only because it
 * constructs and consumes these itself; they are `@internal` and carry no
 * meaning for callers, who should not read or depend on them.
 */
final class ListenerId
{
	/**
	 * @internal Populated and read by the registry; not part of the public API.
	 */
	public function __construct(
		public readonly string $eventType,
		public readonly int $serial
	) {
	}
}
