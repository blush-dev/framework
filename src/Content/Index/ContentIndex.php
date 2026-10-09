<?php

/**
 * Content index interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

/**
 * The queryable store of what the indexer learned about every entry
 * (D-003): the filesystem driver's own (D-606). `PhpIndex` is the
 * default. Its entries and refs as rows (`records()`) are what the
 * driver's content store (`IndexStore`) answers record queries over.
 */
interface ContentIndex
{
	/**
	 * Returns whether an index has been stored.
	 */
	public function exists(): bool;

	/**
	 * Returns the stored index, or an empty one.
	 */
	public function snapshot(): IndexSnapshot;

	/**
	 * Replaces the stored index.
	 *
	 * @throws IndexException When it can't be stored.
	 */
	public function save(IndexSnapshot $snapshot): void;

	/**
	 * Deletes the stored index.
	 */
	public function clear(): void;

	/**
	 * Returns the index's entries and refs as rows (D-649), which also
	 * say where entries are, for queries that name folders or parent
	 * keys.
	 */
	public function records(): SnapshotRecords;
}
