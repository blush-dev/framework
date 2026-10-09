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
 * Where the store that keeps entries says they are: the folders 1.x's
 * `path` and the admin's `in()` and `exceptIn()` name, the keys
 * `whereParent()` and `Entries::named()` name, and where each is kept,
 * for showing. Keys and folders follow parents (`EntryPlaces`, D-656)
 * on every driver; the filesystem driver answers from its index
 * (`IndexLocations`), a store without files from its records
 * (`RecordLocations`).
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

	/**
	 * Returns an entry's key within its type, by id, or `null` for an id
	 * the store doesn't keep.
	 */
	public function key(string $id): ?string;

	/**
	 * Returns where the store keeps an entry, for showing (a file's path
	 * from the content folder), or `''` for a store that keeps no files.
	 */
	public function path(string $id): string;

	/**
	 * Forgets what was read, after a write, so the next question reads
	 * the store again.
	 */
	public function refresh(): void;
}
