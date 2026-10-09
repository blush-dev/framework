<?php

/**
 * Media query.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Blush\Media\MediaKind;

/**
 * What to find in the media index (D-288): files whose path or metadata
 * has `search` in it (any case), of a `kind`, and images without alt
 * text (`missingAlt`), newest first, a page at a time.
 */
final readonly class MediaQuery
{
	public function __construct(
		public string $search = '',
		public ?MediaKind $kind = null,
		public bool $missingAlt = false,
		// Only files this account uploaded, by its id (D-407, D-668).
		public ?string $owner = null,
		public int $page = 1,
		public int $per = 48
	) {}
}
