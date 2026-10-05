<?php

/**
 * Content index interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Query\Query;
use Blush\Content\Query\Selection;

/**
 * The queryable store of what the indexer learned about every entry
 * (D-003). `PhpIndex` is the default; a `SqliteIndex` for large sites and
 * search can replace it by binding `ContentIndex`, compiling queries to
 * SQL instead of array filters.
 */
interface ContentIndex
{
	/**
	 * Returns whether an index has been stored.
	 */
	public function exists(): bool;

	/**
	 * Returns the stored index, or an empty one.
	 */
	public function snapshot(): IndexSnapshot;

	/**
	 * Replaces the stored index.
	 *
	 * @throws IndexException When it can't be stored.
	 */
	public function save(IndexSnapshot $snapshot): void;

	/**
	 * Deletes the stored index.
	 */
	public function clear(): void;

	/**
	 * Returns the paths a query matches, as of a Unix time (which decides
	 * whether published entries are still scheduled).
	 */
	public function select(Query $query, int $now): Selection;
}
