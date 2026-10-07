<?php

/**
 * Filed refs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Blush\Content\Index\IndexReport;

/**
 * What `ContentWriter::fileRefs()` did: the files whose relations it
 * filed, why each file it couldn't change was left, and the reindex
 * that followed.
 */
final readonly class FiledRefs
{
	/**
	 * @param list<string>          $paths  The files written.
	 * @param array<string, string> $failed Messages by path.
	 */
	public function __construct(
		public array $paths,
		public array $failed,
		public IndexReport $index
	) {}
}
