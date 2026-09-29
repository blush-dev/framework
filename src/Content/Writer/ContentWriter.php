<?php

/**
 * Content writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use DateTimeInterface;
use Blush\Content\Type\ContentType;

/**
 * Changes content (D-013, D-228): the write side of `ContentSource`, so a
 * site that stores content elsewhere binds its own. Entries are named by
 * id (their path under the content folder). Every write reindexes and
 * moves the content version on, so pages show the change.
 *
 * `$revision` (from `load()` or an earlier write) guards against lost
 * edits: when the file no longer matches it, the write throws
 * `WriteConflict` and nothing changes. `null` skips the check.
 */
interface ContentWriter
{
	/**
	 * Reads an entry's file for editing.
	 *
	 * @throws WriteException When there's no such file or it can't be read.
	 */
	public function load(string $id): EditableEntry;

	/**
	 * Creates an entry of a type from a slug: `{folder}/{slug}.{format}`,
	 * or `{folder}/{Y-m-d}.{slug}.{format}` for types with date archives
	 * (the date defaults to now).
	 *
	 * @throws WriteException When the file exists or the slug or format is invalid.
	 */
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null, string $format = 'md'): WriteResult;

	/**
	 * Changes an entry's front matter and body.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function update(string $id, EntryChanges $changes, ?string $revision = null): WriteResult;

	/**
	 * Gives an entry a new slug: its file is renamed (keeping any date
	 * prefix), or its folder for a bundle (`{slug}/index.md`, with its
	 * media).
	 *
	 * @throws WriteConflict
	 * @throws WriteException When the new name is taken or invalid.
	 */
	public function rename(string $id, string $slug, ?string $revision = null): WriteResult;

	/**
	 * Deletes an entry: its file, or its bundle's folder, moves to
	 * `storage/trash`, from where it can be restored by hand.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function delete(string $id, ?string $revision = null): WriteResult;
}
