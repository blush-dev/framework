<?php

/**
 * Entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use DateTimeInterface;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Query\QueryRunner;
use Blush\Content\Type\ContentType;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;

/**
 * The one place the rest of the framework gets and changes content
 * (D-003, D-643): entries by id or key, terms, queries, and writes, each
 * naming an entry by its id (D-654), never by where it's kept.
 *
 *     $post  = $content->find('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74');
 *     $about = $content->named('page', 'about');
 *     $posts = $content->query()->type('post')->orderBy('published', Order::Desc)->get();
 *     $post  = $content->change($post->id, new EntryChanges(set: ['title' => 'Hello']), $post->version);
 *
 * Writes go through the storage driver's `ContentWriter` and answer the
 * entry as it is after them. Each names an entry by its id, or takes an
 * `Entry` for its id; an entry without one can't be changed until it has
 * one (D-481), and the write throws `WriteException`. A `$version` (`Entry::$version`, D-648)
 * guards against lost edits: when the entry changed since, the write
 * throws `WriteConflict` and nothing changes; `null` skips the check.
 */
interface Entries extends QueryRunner
{
	/**
	 * Returns a new query that runs against this repository.
	 */
	public function query(): Query;

	/**
	 * Returns an entry by its id (D-477), whatever its status. When files
	 * share an id, the first by path is found.
	 */
	public function find(string $id): ?Entry;

	/**
	 * Returns an entry by type and key (see `IndexRecord`), in a language
	 * (by code; the site's default language when `null`), whatever its
	 * status. A landing page's key is `''`.
	 */
	public function named(string $type, string $key, ?string $language = null): ?Entry;

	/**
	 * Returns a taxonomy term: its entry when it has a file in the
	 * language (the default when `null`), by its key there or by its
	 * original's, the original when it has no translation there, or
	 * `null`. A slug entries name with no file isn't a term (D-584).
	 */
	public function term(string $taxonomy, string $slug, ?string $language = null): ?Entry;

	/**
	 * Returns an entry and its translations (D-455), by language code,
	 * the entry's own included.
	 *
	 * @return array<string, Entry>
	 */
	public function translations(Entry $entry): array;

	/**
	 * Returns an entry's translation in a language (itself for its own
	 * language), whatever its status, or `null`.
	 */
	public function translation(Entry $entry, string $language): ?Entry;

	/**
	 * Returns the key of an entry's parent, by the entry's type and key,
	 * when the parent has a file (in the default language unless another
	 * is given); `null` otherwise. Cheaper than `parent()` when only the
	 * key is needed, such as for a hierarchical term's URL.
	 */
	public function parentKey(string $type, string $key, ?string $language = null): ?string;

	/**
	 * Returns an entry's parent in its own type (see
	 * `ContentType::parentKey()`), whatever its status, or `null` when it
	 * has none or the parent has no file.
	 */
	public function parent(Entry $entry): ?Entry;

	/**
	 * Returns the entries whose parent is this one, whatever their
	 * status, by title.
	 *
	 * @return list<Entry>
	 */
	public function children(Entry $entry): array;

	/**
	 * Returns the entries on either side of one in a listing: by default
	 * its type's own (in the type's order, so newest first for a
	 * collection), in the entry's language. `before` comes just before it
	 * in that order and `after` just after; either is `null` at an end,
	 * and both are for an entry the listing doesn't hold. Give a query to
	 * walk another listing (its limit and offset aside).
	 *
	 * @return array{before: ?Entry, after: ?Entry}
	 */
	public function neighbors(Entry $entry, ?Query $query = null): array;

	/**
	 * Returns how many listed entries reference each term of a taxonomy,
	 * by slug, leaving out slugs with no file (D-584); or, given a query, how many of the
	 * entries it finds do (its limit and offset aside). Several keys
	 * (`ContentTypes::termKeys()`) count entries under any of them, once.
	 *
	 * @param  string|list<string> $taxonomy
	 * @return array<string, int>
	 */
	public function termCounts(string|array $taxonomy, ?Query $query = null): array;

	/**
	 * Returns an entry as it's stored, for editing: its front matter as
	 * written, its Markdown, and its version.
	 *
	 * @throws WriteException When there's no such entry or it can't be read.
	 */
	public function editable(Entry|string $entry): EditableEntry;

	/**
	 * Returns the page a type keeps at a fixed key (see `createAt()`;
	 * `index` for its landing page) as it's stored now, or `null`: for a
	 * type just defined, whose pages lookups may not know yet in the same
	 * request.
	 *
	 * @throws WriteException When the key is invalid or the page can't be read.
	 */
	public function editableAt(ContentType $type, string $key): ?EditableEntry;

	/**
	 * Creates an entry of a type from a slug, at the top of its type or,
	 * for a tree's page, under another of its pages (`$parent`, by id,
	 * D-408), with the changes as its front matter and Markdown. A new
	 * entry is given an id and, when the changes have none, a `published`
	 * date of now (D-514); `$date` dates a collection's file name and
	 * folder patterns (now by default).
	 *
	 * @throws WriteException When the entry exists, the slug is invalid,
	 *                        or the parent isn't a page of the tree.
	 */
	public function create(ContentType $type, string $slug, EntryChanges $changes, Entry|string|null $parent = null, ?DateTimeInterface $date = null): Entry;

	/**
	 * Creates a page a type keeps at a fixed key: its landing page
	 * (`index`), or a page such as a relation archive's list page
	 * (`_cooks`) or one target's (`_cooks/jane`, D-602). Each of the key's
	 * segments is a slug, and may start with `_` to keep it out of
	 * listings.
	 *
	 * @throws WriteException When the page exists or the key is invalid.
	 */
	public function createAt(ContentType $type, string $key, EntryChanges $changes): Entry;

	/**
	 * Copies an entry (D-275) under a new slug, with changes applied to
	 * the copy: `{slug}`, or the first of `{slug}-2`, `{slug}-3`, … that's
	 * free. A landing page can't be copied.
	 *
	 * @throws WriteException
	 */
	public function duplicate(Entry|string $entry, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): Entry;

	/**
	 * Changes an entry's front matter and Markdown.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function change(Entry|string $entry, EntryChanges $changes, ?string $version = null): Entry;

	/**
	 * Gives an entry a new slug, so a new address.
	 *
	 * @throws WriteConflict
	 * @throws WriteException When the slug is taken or invalid, or the
	 *                        entry is a landing page.
	 */
	public function rename(Entry|string $entry, string $slug, ?string $version = null): Entry;

	/**
	 * Moves a tree's page under another of its pages (`$parent`, by id),
	 * or to the top with `null` (D-410), with the pages under it.
	 *
	 * @throws WriteConflict
	 * @throws WriteException When it isn't a tree's page, the parent isn't
	 *                        one of its pages (or is the page itself, or
	 *                        under it), or a page is already there.
	 */
	public function move(Entry|string $entry, Entry|string|null $parent, ?string $version = null): Entry;

	/**
	 * Moves an entry to the trash (D-484): off the site, keeping its
	 * address and id until it's restored or deleted.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function trash(Entry|string $entry, ?string $version = null): Entry;

	/**
	 * Brings an entry back from the trash as `$status`: a draft unless an
	 * Undo puts back the one it had (D-237, D-525), `null` for none (so
	 * published).
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function restore(Entry|string $entry, ?string $version = null, ?Status $status = Status::Draft): Entry;

	/**
	 * Deletes an entry for good.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function delete(Entry|string $entry, ?string $version = null): void;
}
