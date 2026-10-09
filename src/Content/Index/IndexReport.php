<?php

/**
 * Index report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Relation\Resolution;

/**
 * What an indexing run did: which entries it added, changed, and removed
 * (by path), which files it couldn't parse, and whether it wrote the index.
 * A file that fails to parse is left out of the index, so its entry
 * disappears until it's fixed. A run that wrote the index also says
 * which entries' relations Blush would file differently (D-589), so a
 * writer can file both forms of what it just wrote (D-596).
 */
final readonly class IndexReport
{
	/**
	 * @param int                   $total    Entries in the index.
	 * @param list<string>          $added
	 * @param list<string>          $changed
	 * @param list<string>          $removed
	 * @param array<string, string> $failures Error messages, by source path.
	 * @param bool                  $full     Whether every file was parsed again.
	 * @param bool                  $written  Whether the index was stored.
	 * @param array<string, array<string, Resolution>> $stale Relations to file again, by source path and relation name.
	 */
	public function __construct(
		public int $total = 0,
		public array $added = [],
		public array $changed = [],
		public array $removed = [],
		public array $failures = [],
		public bool $full = false,
		public bool $written = false,
		public array $stale = []
	) {}

	/**
	 * Returns whether any entry was added, changed, or removed.
	 */
	public function hasChanges(): bool
	{
		return $this->added !== [] || $this->changed !== [] || $this->removed !== [];
	}

	/**
	 * Returns the paths of every added, changed, or removed entry.
	 *
	 * @return list<string>
	 */
	public function changedIds(): array
	{
		return [...$this->added, ...$this->changed, ...$this->removed];
	}
}
