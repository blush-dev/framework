<?php

/**
 * Media id report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * What `MediaIds::report()` found: the media files missing a valid id,
 * and the files sharing each id held by more than one, by their keys
 * (their paths under `user/media`).
 */
final readonly class MediaIdReport
{
	/**
	 * @param list<string>                $missing    Keys, sorted.
	 * @param array<string, list<string>> $duplicates Keys by id.
	 */
	public function __construct(
		public array $missing = [],
		public array $duplicates = []
	) {}

	/**
	 * Returns whether every media file has an id of its own.
	 */
	public function isClean(): bool
	{
		return $this->missing === [] && $this->duplicates === [];
	}
}
