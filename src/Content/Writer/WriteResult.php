<?php

/**
 * Content write result.
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
 * What a write did: the entry's path (which a rename changes), the
 * file's new revision (`null` once deleted), the reindex that followed,
 * and the other entries it moved, old path to new (a parent made its
 * folder's page, D-408).
 */
final readonly class WriteResult
{
	/**
	 * @param array<string, string> $moved
	 */
	public function __construct(
		public string $path,
		public ?string $revision,
		public IndexReport $index,
		public array $moved = []
	) {}
}
