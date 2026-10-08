<?php

/**
 * Region file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

/**
 * A site region as its data file (`user/data/regions/{name}.json`,
 * D-201) holds it: its raw items. Problems with the file's shape are kept
 * for `theme:check`.
 */
final readonly class RegionFile
{
	/**
	 * @param list<mixed>  $items
	 * @param list<string> $problems
	 */
	public function __construct(
		public string $name,
		public string $location,
		public array $items = [],
		public array $problems = []
	) {}
}
