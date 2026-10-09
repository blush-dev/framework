<?php

/**
 * Locating store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * A record store that can say where a record is kept, for people reading
 * a message or a report (D-672): `user/data/types/movie.json` for a file,
 * `types/movie in the database` for a database. A store that can't
 * say is described by its table and key (`KeyedTable::location()`).
 */
interface LocatingStore
{
	/**
	 * Where the record with a key value, or an id for a table without a
	 * key, is kept, whether it exists or not.
	 */
	public function location(Table $table, string $key): string;
}
