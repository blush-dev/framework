<?php

/**
 * Entry table.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use DateTimeImmutable;
use DateTimeZone;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * The `entries` table (D-649): one for every content type, an entry a
 * record whose fields are its columns:
 *
 * - `type`, `language` (its code), `parent_id` (the parent entry's id,
 *   or null), `slug` (`''` for a type's landing page), `original_id` (a
 *   translation's original, or null);
 * - `status` (`draft`, `published`, or `trash`; "scheduled" is a
 *   published entry whose `published` time is still to come),
 *   `visibility`;
 * - `published` (or null) and `updated`, as UTC times (`time()`), which
 *   sort and compare as text;
 * - `title`, `position` (or null);
 * - `archive` (or null): for a page written for one target's relation
 *   archive in a type that doesn't nest (D-602), its place, `_{word}/{slug}`
 *   (`_authors/jane`), which is its key, since no parent can give it
 *   one (D-657);
 * - `fields`: its front matter (the type's fields, `locale`, and keys
 *   it doesn't declare);
 * - `slugs`: each front matter value written as slugs (a list), for
 *   1.x queries that compare values as slugs (`meta_key`, and terms
 *   written without refs), worked out by core (`EntryValues`).
 *
 * The record's content is the entry's Markdown. What an entry refers
 * to is refs (`Ref`), never its fields.
 */
final class EntryTable
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'entries';

	/**
	 * Returns the table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Content, fields: [
			'type', 'language', 'parent_id', 'slug', 'original_id', 'status', 'visibility', 'published', 'updated', 'title', 'position', 'archive'
		]);
	}

	/**
	 * Returns a Unix time as the table keeps it: UTC, ISO 8601, to the
	 * second (`2026-10-08T17:39:00Z`), so times sort and compare as text.
	 */
	public static function time(int $timestamp): string
	{
		return new DateTimeImmutable("@{$timestamp}")->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}

	/**
	 * Returns a time as the table keeps it as a Unix time, or `null` for
	 * a value that isn't one.
	 */
	public static function timestamp(mixed $time): ?int
	{
		// Read by hand, by position: every entry built may read two.
		if (! is_string($time) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $time) !== 1) {
			return null;
		}

		$timestamp = gmmktime((int) substr($time, 11, 2), (int) substr($time, 14, 2), (int) substr($time, 17, 2), (int) substr($time, 5, 2), (int) substr($time, 8, 2), (int) substr($time, 0, 4));

		return $timestamp === false ? null : $timestamp;
	}
}
