<?php

/**
 * Editable entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

/**
 * An entry's file as it is on disk, for an editor: its path under the
 * content folder, its front matter as written (keys as the file has
 * them, aliases included), its raw body, a revision (a hash of the
 * file) to send back with changes, so a change made meanwhile isn't
 * overwritten, and when the file was last written (a Unix timestamp), if
 * known.
 */
final readonly class EditableEntry
{
	/**
	 * @param array<array-key, mixed> $frontMatter
	 */
	public function __construct(
		public string $path,
		public array $frontMatter,
		public string $body,
		public string $revision,
		public ?int $modified = null
	) {}
}
