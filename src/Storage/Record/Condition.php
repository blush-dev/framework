<?php

/**
 * Condition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

/**
 * One condition of a record query: a key, an operator, and the value to
 * compare with. The key is a value's key, dotted to reach into nested
 * values (`seo.title`), or `id` or `body`.
 */
final readonly class Condition
{
	/**
	 * @throws InvalidRecordQuery When the key is empty or the value doesn't fit the operator.
	 */
	public function __construct(
		public string $key,
		public Operator $operator,
		public mixed $value = null
	) {
		RecordQuery::checkKey($key);
		$operator->check($value);
	}
}
