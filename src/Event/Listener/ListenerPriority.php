<?php

/**
 * Listener priority enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

/**
 * Named presets for the common listener priorities, usable anywhere an integer
 * priority is accepted. Listeners run lowest number first, so the cases name the
 * *order* rather than a magnitude: `First` runs before every other listener,
 * `Last` after every other listener, and `Normal` is the default `0`. Because
 * `First` and `Last` are the integer extremes they act as true bookends; pass a
 * plain integer for any ordering in between.
 *
 * The backing integers are an implementation detail of ordering, so the enum is
 * intentionally not int-backed: `toInt()` maps each case to its value, keeping
 * the platform-dependent extremes (`PHP_INT_MIN` / `PHP_INT_MAX`) out of the
 * public surface.
 */
enum ListenerPriority
{
	case First;
	case Normal;
	case Last;

	/**
	 * Returns the integer priority this case represents.
	 */
	public function toInt(): int
	{
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- valid $this in an enum method; the sniff predates enums.
		return match ($this) {
			self::First  => PHP_INT_MIN,
			self::Normal => 0,
			self::Last   => PHP_INT_MAX
		};
	}
}
