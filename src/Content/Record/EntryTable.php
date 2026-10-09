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
			'type', 'language', 'parent_id', 'slug', 'original_id', 'status', 'visibility', 'published', 'updated', 'title', 'position'
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
}
