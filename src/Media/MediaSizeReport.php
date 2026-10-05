<?php

/**
 * Media size report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * What `MediaSizes::report()` found, by the key of each image it's
 * about: its sizes that its metadata file doesn't list, or lists with
 * other dimensions (`unrecorded`), and what it lists that isn't one of
 * its sizes (`stale`: a file that's gone, or another image's).
 */
final readonly class MediaSizeReport
{
	/**
	 * @param array<string, list<string>> $unrecorded Size keys by image key.
	 * @param array<string, list<string>> $stale      Listed keys by image key.
	 */
	public function __construct(
		public array $unrecorded = [],
		public array $stale = []
	) {}

	/**
	 * Returns whether every image lists exactly its sizes.
	 */
	public function isClean(): bool
	{
		return $this->unrecorded === [] && $this->stale === [];
	}

	/**
	 * The keys of the images whose lists need writing, sorted.
	 *
	 * @return list<string>
	 */
	public function images(): array
	{
		$keys = array_map(strval(...), array_keys($this->unrecorded + $this->stale));
		sort($keys);

		return $keys;
	}

	/**
	 * How many sizes aren't recorded as they are.
	 */
	public function count(): int
	{
		return array_sum(array_map(count(...), $this->unrecorded));
	}
}
