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
 * An entry as it's stored, for an editor: its id (`''` for a file
 * without one), its front matter as written (keys as stored, a file's
 * aliases included), its Markdown, a version (D-648; a hash of a file)
 * to send back with changes, so a change made meanwhile isn't
 * overwritten, when it was last written (a Unix timestamp), if known,
 * and where it's kept, for showing (a file's path in the content
 * folder), if the driver says.
 */
final readonly class EditableEntry
{
	/**
	 * @param array<array-key, mixed> $frontMatter
	 */
	public function __construct(
		public string $id,
		public array $frontMatter,
		public string $body,
		public string $version,
		public ?int $modified = null,
		public ?string $path = null
	) {}
}
