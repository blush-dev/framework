<?php

/**
 * File name rename.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * One entry `FileNames` would rename to its type's pattern (D-512): its
 * path, where it goes, and every file that moves with it (its own, and
 * its translations linked by name).
 */
final readonly class FileNameRename
{
	/**
	 * @param array<string, string> $moves Old paths to new, the entry's own first.
	 */
	public function __construct(
		public string $type,
		public string $path,
		public string $to,
		public array $moves
	) {}
}
