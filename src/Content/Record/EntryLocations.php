<?php

/**
 * Entry locations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

/**
 * What only the store that keeps entries can say about where they are,
 * for the query arguments that name places rather than values: the
 * folders 1.x's `path` and the admin's `in()` and `exceptIn()` name, and
 * the keys `whereParent()` names. The filesystem driver answers from its
 * index; until queries name parents and types instead (the data layer's
 * step 3d), a database driver answers from its own structure.
 */
interface EntryLocations
{
	/**
	 * Returns the ids of the entries listed in any of these folders,
	 * by path from the content folder (`''` for its root).
	 *
	 * @param  list<string> $folders
	 * @return list<string>
	 */
	public function idsIn(array $folders): array;

	/**
	 * Returns the ids of the entries with a key, in any type and
	 * language.
	 *
	 * @return list<string>
	 */
	public function idsWithKey(string $key): array;
}
