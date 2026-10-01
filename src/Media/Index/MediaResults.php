<?php

/**
 * Media results.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

/**
 * A page of what a media query found, and how many it found in all.
 */
final readonly class MediaResults
{
	/**
	 * @param list<MediaRecord> $records
	 */
	public function __construct(
		public array $records,
		public int $total
	) {}
}
