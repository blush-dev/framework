<?php

/**
 * Data store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Closure;

/**
 * Keeps the site's data area (D-486, D-642): settings, types, field
 * sets, relations, menus, regions, redirects, media metadata, and what
 * extensions keep there. A record is a map (or list) of values under a
 * name, which may use `/` to group records in folders (`types/post`,
 * `media/2024/sunset.jpg`), but never `..`. The filesystem driver keeps
 * each as a JSON file in `user/data` (`FileDataStore`); a database
 * driver keeps them as rows.
 *
 * Everything else reaches the data area through this, never through
 * `user/data`'s files, so a site can keep its data in a database.
 */
interface DataStore
{
	/**
	 * Returns whether a record exists.
	 *
	 * @throws InvalidData When the name is unsafe.
	 */
	public function has(string $name): bool;

	/**
	 * Returns a record, or `null` when there's none, so callers can tell
	 * "missing" from "empty".
	 *
	 * @return ?array<array-key, mixed>
	 * @throws InvalidData When the name is unsafe or the record can't be read.
	 */
	public function load(string $name): ?array;

	/**
	 * Returns the records directly in a folder, by their names in it,
	 * sorted. A missing folder has none.
	 *
	 * @return array<string, array<array-key, mixed>>
	 * @throws InvalidData When the folder is unsafe or a record can't be read.
	 */
	public function loadAll(string $folder): array;

	/**
	 * Returns every record in a folder and the folders under it, by name
	 * relative to it, each with the time it last changed, sorted. They
	 * aren't read, so one that can't be is still listed.
	 *
	 * @return array<string, int>
	 * @throws InvalidData When the folder is unsafe.
	 */
	public function records(string $folder): array;

	/**
	 * Writes a record whole, creating it when it's new.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidData When the name is unsafe.
	 * @throws DataStoreException When it can't be written.
	 */
	public function save(string $name, array $data): void;

	/**
	 * Removes a record. A missing one is nothing to remove.
	 *
	 * @throws InvalidData When the name is unsafe.
	 * @throws DataStoreException When it can't be removed.
	 */
	public function delete(string $name): void;

	/**
	 * Runs `$write` so no other transaction's writes interleave with it,
	 * and returns what it returns. When it throws, every record it saved
	 * or deleted is put back as it was, and the error goes on. A
	 * transaction inside another is part of it, but puts back its own
	 * writes when it fails, so the outer one can go on without them.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws DataStoreException When the store can't be locked.
	 */
	public function transaction(Closure $write): mixed;

	/**
	 * Where a record is kept, for people reading a message or a report:
	 * `user/data/types/post.json` for a file.
	 *
	 * @throws InvalidData When the name is unsafe.
	 */
	public function location(string $name): string;
}
