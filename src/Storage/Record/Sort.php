<?php

/**
 * Sort.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * One key a query orders by, and which way.
 */
final readonly class Sort
{
	/**
	 * @throws InvalidRecordQuery When the key is empty.
	 */
	public function __construct(
		public string $key,
		public Order $order = Order::Asc
	) {
		RecordQuery::checkKey($key);
	}
}
