<?php

/**
 * Query selection.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * What an index returns for a query: the paths within the limit and offset,
 * in order, and how many entries matched in all.
 */
final readonly class Selection
{
	/**
	 * @param list<string> $paths
	 */
	public function __construct(
		public array $paths = [],
		public int $total = 0
	) {}
}
