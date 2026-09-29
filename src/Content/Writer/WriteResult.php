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
 * What a write did: the entry's id (its path, which a rename changes),
 * the file's new revision (`null` once deleted), and the reindex that
 * followed.
 */
final readonly class WriteResult
{
	public function __construct(
		public string $id,
		public ?string $revision,
		public IndexReport $index
	) {}
}
