<?php

/**
 * Schema store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * A record store with a schema of its own to keep (D-644): a database
 * driver's, which makes a table, its columns, and its indexes from the
 * table's definition. Stores make a table on first use; `storage:sync`
 * makes every registered one ahead of time, as a deploy does. Files need
 * no schema, so the filesystem driver's store isn't one.
 */
interface SchemaStore
{
	/**
	 * Makes a table, or brings it up to date with its definition: the
	 * columns and indexes of fields it declares now.
	 *
	 * @throws RecordStoreFailure
	 */
	public function prepare(Table $table): void;

	/**
	 * Gathers statistics on what the tables hold, so queries pick the
	 * right index: after a large load, such as a copy.
	 *
	 * @throws RecordStoreFailure
	 */
	public function analyze(): void;
}
