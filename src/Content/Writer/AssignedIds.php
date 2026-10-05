<?php

/**
 * Assigned ids.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Blush\Content\Index\IndexReport;

/**
 * What `ContentWriter::assignIds()` did: the new id of each file it
 * changed, why each file it couldn't change was left, and the reindex
 * that followed.
 */
final readonly class AssignedIds
{
	/**
	 * @param array<string, string> $ids    New ids by path.
	 * @param array<string, string> $failed Messages by path.
	 */
	public function __construct(
		public array $ids,
		public array $failed,
		public IndexReport $index
	) {}
}
