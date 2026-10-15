<?php

/**
 * Test type supports.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

/**
 * The Supports switches (D-866) test fixtures' types get when they don't
 * set them, as types had before the switches: `image`, `summary`,
 * `subtitle`, and `visibility`, and `position` for a tree or a collection
 * ordered by position. A missing switch is off, so a test that's about
 * one sets it itself.
 */
final class TypeSupports
{
	/**
	 * Returns a type definition with the switches it doesn't set turned
	 * on.
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array<array-key, mixed>
	 */
	public static function defaults(array $definition): array
	{
		$positioned = ($definition['kind'] ?? null) === 'tree' || ($definition['order'] ?? null) === 'position';

		return [...$definition, ...array_diff_key(['image' => true, 'summary' => true, 'subtitle' => true, 'visibility' => true, 'position' => $positioned], $definition)];
	}
}
