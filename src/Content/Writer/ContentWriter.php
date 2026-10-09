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
use Blush\Content\Status;
use Blush\Content\Type\ContentType;

/**
 * Changes content (D-013, D-228): what a storage driver gives `Entries`
 * (D-643) to write with, every entry named by its id (D-477), never by
 * where it's kept. Every new entry, a copy included, is given an id,
 * one the changes set when no entry has it. An entry kept without one
 * (a 1.x file, D-481) is named by the steady id its record has (D-652)
 * and given one of its own when it's changed, so each write returns the
 * entry's id after it. Every write moves the content version on, so
 * pages show the change.
 *
 * `$version` (an entry's, from `Entries` or `load()`) guards against
 * lost edits (D-648): when the entry no longer matches it, the write
 * throws `WriteConflict` and nothing changes. `null` skips the check.
 *
 * How an entry is kept is the driver's: on files, what a write means for
 * a file's name and folder (D-511, D-629), a `slug` key, or the pages
 * under a tree's page is the filesystem driver's (D-654).
 */
interface ContentWriter
{
	/**
	 * Reads an entry for editing: its front matter as stored, its
	 * Markdown, and its version.
	 *
	 * @throws WriteException When there's no such entry or it can't be read.
	 */
	public function load(string $id): EditableEntry;

	/**
	 * Reads the page a type keeps at a fixed key (see `createAt()`;
	 * `index` for its landing page) as it's stored now, or `null` when
	 * there's none: for a type just defined, whose pages a store's lookups
	 * may not know yet.
	 *
	 * @throws WriteException When the key is invalid or the page can't be read.
	 */
	public function loadAt(ContentType $type, string $key): ?EditableEntry;

	/**
	 * Creates an entry of a type from a slug, at the top of its type or,
	 * for a tree's page, under another of its pages (`$parent`, by id,
	 * D-408). A collection's entry is dated `$date` (now by default) for
	 * its type's patterns. Returns its id.
	 *
	 * @throws WriteException When the entry exists, the slug is invalid,
	 *                        or the parent isn't a page of a tree.
	 */
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?string $parent = null, ?DateTimeInterface $date = null): string;

	/**
	 * Creates a page a type keeps at a fixed key, undated: its landing
	 * page (`index`), or a page such as a relation archive's list page
	 * (`_cooks`) or one target's (`_cooks/jane`, D-602). Each of the
	 * key's segments is a slug, and may start with `_` to keep it out of
	 * listings. Returns its id.
	 *
	 * @throws WriteException When the page exists or the key is invalid.
	 */
	public function createAt(ContentType $type, string $key, EntryChanges $changes): string;

	/**
	 * Copies an entry beside it (D-275) under a new slug, with changes
	 * applied to the copy: `{slug}`, or the first of `{slug}-2`,
	 * `{slug}-3`, … that's free, dated `$date` (now by default). A
	 * landing page can't be copied. Returns the copy's id.
	 *
	 * @throws WriteException When the entry can't be read or the copy written.
	 */
	public function duplicate(string $id, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): string;

	/**
	 * Changes an entry's front matter and Markdown.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function update(string $id, EntryChanges $changes, ?string $version = null): string;

	/**
	 * Gives an entry a new slug.
	 *
	 * @throws WriteConflict
	 * @throws WriteException When the slug is taken or invalid, or the
	 *                        entry is a landing page.
	 */
	public function rename(string $id, string $slug, ?string $version = null): string;

	/**
	 * Moves a tree's page under another of its pages (`$parent`, by id),
	 * or to the top with `null` (D-410), keeping its slug, with the pages
	 * under it. Moving to where it is changes nothing.
	 *
	 * @throws WriteConflict
	 * @throws WriteException When it isn't a tree's page, the parent isn't
	 *                        one of its pages (or is the page itself, or
	 *                        under it), or a page is already there.
	 */
	public function move(string $id, ?string $parent, ?string $version = null): string;

	/**
	 * Moves an entry to the trash (D-484): `status: trash` and `trashed`
	 * (when), so it's off the site but keeps its address and id until
	 * it's restored or deleted.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function trash(string $id, ?string $version = null): string;

	/**
	 * Brings an entry back from the trash (D-484): its `status` becomes
	 * `$status`, a draft unless an Undo puts back the one it had (D-237,
	 * D-525), or goes when that's `null` (published, as an entry that names
	 * none is), and its `trashed` date goes.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function restore(string $id, ?string $version = null, ?Status $status = Status::Draft): string;

	/**
	 * Deletes an entry for good.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function delete(string $id, ?string $version = null): void;
}
