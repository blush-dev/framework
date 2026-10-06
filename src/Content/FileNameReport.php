<?php

/**
 * File name report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * What `FileNames::report()` found (D-512): the entries named by another
 * pattern than their type's, and the ones it leaves as they are, with
 * why, each by type.
 */
final readonly class FileNameReport
{
	/**
	 * @param array<string, list<FileNameRename>>   $renames By type.
	 * @param array<string, array<string, string>> $skipped Why, by type and path.
	 */
	public function __construct(
		public array $renames = [],
		public array $skipped = []
	) {}

	/**
	 * Returns whether every entry is named by its type's pattern.
	 */
	public function isClean(): bool
	{
		return $this->renames === [];
	}

	/**
	 * Returns the renames, of one type or every one.
	 *
	 * @return list<FileNameRename>
	 */
	public function renames(?string $type = null): array
	{
		return $type === null ? array_merge([], ...array_values($this->renames)) : $this->renames[$type] ?? [];
	}
}
