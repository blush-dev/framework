<?php

/**
 * Entry position.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

/**
 * An entry's place among its siblings (D-412): the `position` field a
 * tree's pages and a taxonomy's terms have, a whole number, lowest
 * first. Entries without one come after those with one, by title, so a
 * site that never sets it keeps its title order.
 */
final class Position
{
	/**
	 * The field's name.
	 */
	public const string FIELD = 'position';

	/**
	 * Returns an entry's position, or `null` without one.
	 */
	public static function of(Entry $entry): ?int
	{
		$position = $entry->fields[self::FIELD] ?? null;

		return is_int($position) ? $position : null;
	}

	/**
	 * Compares two entries as siblings: by position, then title.
	 */
	public static function siblings(Entry $a, Entry $b): int
	{
		return self::compare(self::of($a), $a->title, self::of($b), $b->title);
	}

	/**
	 * Compares two positions, either missing, then two titles. `$order`
	 * is 1 for lowest first or -1 for highest; entries without a
	 * position come last either way, by title.
	 */
	public static function compare(?int $a, string $titleA, ?int $b, string $titleB, int $order = 1): int
	{
		return match (true) {
			$a !== null && $b !== null && $a !== $b => $order * ($a <=> $b),
			$a !== null && $b === null              => -1,
			$a === null && $b !== null              => 1,
			default                                 => strnatcasecmp($titleA, $titleB)
		};
	}
}
