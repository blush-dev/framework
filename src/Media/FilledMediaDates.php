<?php

/**
 * Filled media upload dates.
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
 * What `MediaDates` wrote: the upload date given each media file it
 * changed, why each file it couldn't change was left, and the reindex
 * that followed.
 */
final readonly class FilledMediaDates
{
	/**
	 * @param array<string, string> $dates  Upload dates by key, as written.
	 * @param array<string, string> $failed Messages by key.
	 */
	public function __construct(
		public array $dates,
		public array $failed,
		public MediaIndexReport $index
	) {}
}
