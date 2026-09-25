<?php

/**
 * Query runner interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * Runs queries. A `Query` built by a runner (such as the repository's
 * `query()`) carries it, so the builder's `get()` and `paginate()` work at
 * the end of a chain.
 */
interface QueryRunner
{
	/**
	 * Returns the entries a query matches, within its limit and offset.
	 */
	public function get(Query $query): EntryCollection;

	/**
	 * Returns one page of the entries a query matches. The query's own
	 * limit and offset are replaced.
	 */
	public function paginate(Query $query, int $perPage, int $page = 1): Paginator;

	/**
	 * Returns how many entries a query matches, ignoring its limit and
	 * offset.
	 */
	public function count(Query $query): int;
}
