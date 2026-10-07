<?php

/**
 * Snapshot targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Override;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\IndexSnapshot;

/**
 * Finds targets in the content index (D-589). A written value is a key
 * (a slug, or a tree's path), found in the source's language first,
 * since each language has its own keys (D-457), then in any. Links point
 * at originals, so a translation found that way answers its original's
 * id. Entries without an id aren't targets: a link is between ids.
 */
final readonly class SnapshotTargets implements TargetLookup
{
	public function __construct(private IndexSnapshot $snapshot)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $type, string $written, string $language): ?string
	{
		$path = $this->snapshot->find($language, $type, $written);

		foreach ($path === null ? array_keys($this->snapshot->keys) : [] as $other) {
			$path ??= $this->snapshot->find($other, $type, $written);
		}

		return $path === null ? null : $this->originalOfPath($path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function typeOf(string $id): ?string
	{
		$path = $this->snapshot->path($id);

		return $path === null ? null : $this->snapshot->records[$path]['type'] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function written(string $id): ?string
	{
		$path   = $this->snapshot->path($id);
		$record = $path === null ? null : $this->snapshot->records[$path] ?? null;

		if ($record === null) {
			return null;
		}

		return $record['untranslated']['key'] ?? $record['key'];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function original(string $id): ?string
	{
		$path = $this->snapshot->path($id);

		return $path === null ? null : $this->originalOfPath($path);
	}

	/**
	 * Returns the id of the original of the entry at a path: its own
	 * unless it's a translation (a file with a language suffix), else the
	 * entry of its translation group without one.
	 */
	private function originalOfPath(string $path): ?string
	{
		$record = $this->snapshot->records[$path] ?? null;

		if ($record === null) {
			return null;
		}

		if ($record['original'] === null) {
			return $record['id'];
		}

		foreach ($this->snapshot->translations[IndexRecord::groupOf($record)] ?? [] as $sibling) {
			$other = $this->snapshot->records[$sibling] ?? null;

			if ($other !== null && $other['original'] === null) {
				return $other['id'];
			}
		}

		return null;
	}
}
