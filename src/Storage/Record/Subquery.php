<?php

/**
 * Subquery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * One key's values from the records a query finds, for `in` and
 * `not in` (SQL's `IN (SELECT …)`): in the outer query's table, or in
 * another of the same area, which one store keeps.
 *
 *     $query->where('id', 'not in', new Subquery(
 *         new RecordQuery()->where('language', '=', 'fr'),
 *         'original_id'
 *     ));
 *
 * Only plain values (text, numbers, true and false) are collected; the
 * subquery's order, limit, and offset apply.
 */
final readonly class Subquery
{
	/**
	 * @throws InvalidRecordQuery When the key is empty.
	 */
	public function __construct(
		public RecordQuery $query,
		public string $key,
		public ?Table $table = null
	) {
		RecordQuery::checkKey($key);
	}
}
