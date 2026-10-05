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
 * their path under the content folder. Every new entry, a copy
 * included, is given an id (D-477), written last in its front matter,
 * and an update gives one to a file without a valid one. Every write reindexes and
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
	public function load(string $path): EditableEntry;

	/**
	 * Creates an entry of a type from a slug: `{folder}/{slug}.{format}`,
	 * or `{folder}/{Y-m-d}.{slug}.{format}` for types with date archives
	 * (the date defaults to now).
	 *
	 * @throws WriteException When the file exists or the slug or format is invalid.
	 */
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null, string $format = 'md'): WriteResult;

	/**
	 * Creates a page a type keeps at a fixed key in its folder, undated:
	 * `{folder}/{key}.{format}`. Each of the key's segments is a slug, and
	 * may start with `_` to keep it out of listings, such as a people
	 * field's `_cooks` or `_cooks/jane` (D-353).
	 *
	 * @throws WriteException When the file exists or the key or format is invalid.
	 */
	public function createAt(ContentType $type, string $key, EntryChanges $changes, string $format = 'md'): WriteResult;

	/**
	 * Creates a page of a tree under another (D-408), undated:
	 * `{folder}/{parent key}/{slug}.{format}`, so its key is the parent's
	 * and its slug. A parent kept as a file named for its key
	 * (`about.md`) becomes its folder's page first (`about/index.md`), so
	 * a page and its children share a folder; its key and address stay
	 * the same, and the result's `moved` names its new path. A parent
	 * that's already a folder's page, or whose file name says more than
	 * its key (an order prefix, a `slug:` of its own), stays where it is.
	 *
	 * @throws WriteException When the parent isn't a page of a tree, its
	 *                        folder already has a page, the page exists,
	 *                        or the slug or format is invalid.
	 */
	public function createUnder(string $parentPath, string $slug, EntryChanges $changes, string $format = 'md'): WriteResult;

	/**
	 * Copies an entry beside it (D-275) under a new slug, with changes
	 * applied to the copy: `{slug}`, or the first of `{slug}-2`,
	 * `{slug}-3`, … that's free. A dated file takes the date given
	 * (default now) in place of its own. A bundle's copy is a copy of its
	 * folder, media and all. A landing page can't be copied; its name is
	 * its folder's.
	 *
	 * @throws WriteException When the entry can't be read or the copy written.
	 */
	public function duplicate(string $path, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): WriteResult;

	/**
	 * Changes an entry's front matter and body.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function update(string $path, EntryChanges $changes, ?string $revision = null): WriteResult;

	/**
	 * Gives an entry a new slug: its file is renamed (keeping any date
	 * prefix), or its folder for a bundle (`{slug}/index.md`, with its
	 * media).
	 *
	 * @throws WriteConflict
	 * @throws WriteException When the new name is taken or invalid.
	 */
	public function rename(string $path, string $slug, ?string $revision = null): WriteResult;

	/**
	 * Moves a tree's page under another of its pages, or to the top with
	 * `null` (D-410), keeping its slug: its file (or its folder, for one
	 * kept as `{slug}/index.md`) moves into the new parent's folder, with
	 * the folder of pages under it, so they come along. A new parent kept
	 * as a file named for its key becomes its folder's page first, as in
	 * `createUnder()`. Moving to where it already is changes nothing. The
	 * result's `moved` names every entry that moved, old path to new.
	 *
	 * @throws WriteConflict
	 * @throws WriteException When it isn't a tree's page, the parent isn't
	 *                        one of its pages (or is the page itself, or
	 *                        under it), or a page is already there.
	 */
	public function move(string $path, ?string $parentPath, ?string $revision = null): WriteResult;

	/**
	 * Deletes an entry: its file, or its bundle's folder, moves to the
	 * trash, from where `restore()` brings it back.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function delete(string $path, ?string $revision = null): WriteResult;

	/**
	 * Returns the entries in the trash, most recently trashed first.
	 *
	 * @return list<TrashedEntry>
	 */
	public function trashed(): array;

	/**
	 * Reads an entry in the trash, to look at before restoring it (D-276).
	 * Its path is the one it had, and has again once restored.
	 *
	 * @throws WriteException When there's no such entry in the trash, or
	 *                        it can't be read.
	 */
	public function loadTrashed(string $name): EditableEntry;

	/**
	 * Brings an entry back from the trash to where it was, after making
	 * the changes to it (such as `status: draft`), so it's never back
	 * without them. It keeps its id (D-481) unless `$newId` asks for a
	 * new one; one without an id is given one.
	 *
	 * @throws IdTaken When another entry has its id now; restore it with
	 *                 `$newId`, or leave it in the trash.
	 * @throws WriteException When there's no such entry in the trash, or
	 *                        something now has its place.
	 */
	public function restore(string $name, EntryChanges $changes = new EntryChanges(), bool $newId = false): WriteResult;

	/**
	 * Gives entries new ids (D-477), for `content:ids` and Content health:
	 * each file's `id` is set to a new UUIDv7 (added last when it has
	 * none), all in one reindex. A file that can't be read or changed is
	 * left as it is and named in the result.
	 *
	 * @param list<string> $paths
	 */
	public function assignIds(array $paths): AssignedIds;

	/**
	 * Deletes an entry in the trash for good.
	 *
	 * @throws WriteException When there's no such entry in the trash.
	 */
	public function purge(string $name): void;
}
