<?php

/**
 * Source file stat.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Source;

/**
 * One content document in a source: its path relative to the content root
 * (with forward slashes), when it last changed, and its size. The indexer
 * compares these to skip unchanged files without reading them.
 */
final readonly class SourceFile
{
	public function __construct(
		public string $path,
		public int $modified,
		public int $size
	) {}
}
