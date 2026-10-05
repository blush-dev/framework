<?php

/**
 * Recorded media sizes.
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
 * What `MediaSizes::record()` wrote: the sizes now listed for each image
 * it changed, by the image's key, why each image it couldn't change was
 * left, and the reindex that followed.
 */
final readonly class RecordedMediaSizes
{
	/**
	 * @param array<string, list<string>> $images Size keys by image key.
	 * @param array<string, string>       $failed Messages by image key.
	 */
	public function __construct(
		public array $images,
		public array $failed,
		public MediaIndexReport $index
	) {}
}
