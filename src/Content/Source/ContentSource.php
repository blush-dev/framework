<?php

/**
 * Content source interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Source;

/**
 * Where raw content documents come from (D-003). The default reads
 * `user/content` from the filesystem; a Git, S3, or database source can
 * replace it by binding `ContentSource` in a provider. Paths are relative
 * to the source's root and use forward slashes.
 */
interface ContentSource
{
	/**
	 * Returns every content document, sorted by path.
	 *
	 * @return list<SourceFile>
	 * @throws UnreadableSource
	 */
	public function files(): array;

	/**
	 * Returns a document's stat, or `null` when it doesn't exist.
	 *
	 * @throws UnreadableSource When the path is unsafe.
	 */
	public function stat(string $path): ?SourceFile;

	/**
	 * Returns a document's contents.
	 *
	 * @throws UnreadableSource
	 */
	public function read(string $path): string;
}
