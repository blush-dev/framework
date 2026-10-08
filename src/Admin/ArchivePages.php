<?php

/**
 * Archive pages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\Entry\Entry;
use Blush\Content\Http\RelatedController;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;

/**
 * The pages a type keeps for its relation archives (D-602, once people
 * fields' D-351), in its folder:
 *
 * - an archive's **list page**, `_cooks`, which introduces the list of
 *   what the relation links to. The admin pins it in its type's list
 *   below the index page; it's edited like any entry but not duplicated.
 * - a **target page**, `_cooks/jane`, written for one target's archive
 *   under the relation. These are created on demand and never listed.
 */
final readonly class ArchivePages
{
	public function __construct(private ContentTypes $types)
	{}

	/**
	 * Returns the relation an entry is its type's list page for, or
	 * `null`.
	 */
	public function relationOf(Entry $entry): ?Relation
	{
		return array_find($this->types->relationArchives($entry->type), static fn (Relation $relation): bool => $entry->key === RelatedController::word($relation));
	}

	/**
	 * Whether an entry is one of its type's list pages.
	 */
	public function isList(Entry $entry): bool
	{
		return $this->relationOf($entry) !== null;
	}

	/**
	 * Returns the relation an entry is a target page under, or `null`.
	 */
	public function targetRelationOf(Entry $entry): ?Relation
	{
		return array_find($this->types->relationArchives($entry->type), static fn (Relation $relation): bool => dirname($entry->key) === RelatedController::word($relation));
	}

	/**
	 * Whether an entry is a page written for one target's archive.
	 */
	public function isTarget(Entry $entry): bool
	{
		return $this->targetRelationOf($entry) !== null;
	}

	/**
	 * Returns the keys of a type's list pages.
	 *
	 * @return list<string>
	 */
	public function listPages(ContentType $type): array
	{
		return array_values(array_map(RelatedController::word(...), $this->types->relationArchives($type)));
	}

	/**
	 * Returns the folders a type's target pages sit in, under the content
	 * root.
	 *
	 * @return list<string>
	 */
	public function targetFolders(ContentType $type): array
	{
		return array_map(static fn (string $key): string => trim("{$type->folder}/{$key}", '/'), $this->listPages($type));
	}
}
