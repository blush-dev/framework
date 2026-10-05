<?php

/**
 * Assigned media ids.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Media\Index\MediaIndexReport;

/**
 * What `MediaIds` fixed: the new id of each media file it changed, why
 * each file it couldn't change was left, and the reindex that followed.
 */
final readonly class AssignedMediaIds
{
	/**
	 * @param array<string, string> $ids    New ids by key.
	 * @param array<string, string> $failed Messages by key.
	 */
	public function __construct(
		public array $ids,
		public array $failed,
		public MediaIndexReport $index
	) {}
}
