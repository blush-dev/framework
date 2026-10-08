<?php

/**
 * Media index report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

/**
 * What a media indexing run did: how many files it holds, the keys it
 * added, changed, and removed, the metadata files with no media file
 * (`orphans`), whether it wrote the index, and how many files it left to
 * read when it was given a limit (`pending`, D-626).
 */
final readonly class MediaIndexReport
{
	/**
	 * @param list<string> $added
	 * @param list<string> $changed
	 * @param list<string> $removed
	 * @param list<string> $orphans
	 */
	public function __construct(
		public int $total = 0,
		public array $added = [],
		public array $changed = [],
		public array $removed = [],
		public array $orphans = [],
		public bool $written = false,
		public int $pending = 0
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'total'   => $this->total,
			'added'   => count($this->added),
			'changed' => count($this->changed),
			'removed' => count($this->removed),
			'orphans' => $this->orphans,
			'pending' => $this->pending
		];
	}
}
