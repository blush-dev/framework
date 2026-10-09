<?php

/**
 * Entry files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;

/**
 * The entry kept in a content file, by its path: for the filesystem
 * driver's own tools (D-654), such as Site Health's checks of files,
 * which find problems by file, a file without an id among them. Code
 * that isn't about files finds entries through `Entries`, by id or key.
 */
final readonly class EntryFiles
{
	public function __construct(
		private IndexFreshness $freshness,
		private EntryHydrator $hydrator
	) {}

	/**
	 * Returns the entry kept at a path, whatever its status, or `null`.
	 */
	public function at(string $path): ?Entry
	{
		$record = $this->freshness->fresh()->snapshot()->record($path);

		return $record === null ? null : $this->hydrator->hydrate($record);
	}
}
