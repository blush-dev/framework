<?php

/**
 * Renamed files.
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
 * What `ContentWriter::renameFiles()` did: each entry's new path, by its
 * old one, why each entry it couldn't rename was left, and the reindex
 * that followed.
 */
final readonly class RenamedFiles
{
	/**
	 * @param array<string, string> $renamed New paths by old path.
	 * @param array<string, string> $failed  Messages by path.
	 */
	public function __construct(
		public array $renamed,
		public array $failed,
		public IndexReport $index
	) {}
}
