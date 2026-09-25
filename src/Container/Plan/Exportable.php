<?php

/**
 * Exportable value check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use UnitEnum;

/**
 * Decides whether a value survives a round trip through `var_export()` and a
 * PHP include unchanged: scalars, `null`, enum cases, and arrays of those.
 * Closures and other objects do not, so a plan holding one is kept in memory
 * only and rebuilt from reflection on each request.
 */
final class Exportable
{
	/**
	 * Whether the value is exportable.
	 */
	public static function check(mixed $value): bool
	{
		if (is_array($value)) {
			return array_all($value, static fn (mixed $item): bool => self::check($item));
		}

		return $value === null || is_scalar($value) || $value instanceof UnitEnum;
	}
}
