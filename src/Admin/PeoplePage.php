<?php

/**
 * People page.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\PeopleField;

/**
 * The pages a type keeps for its people fields (D-351), in its folder:
 *
 * - a field's **list page**, `_cooks`, which introduces the list of
 *   people the field credits. The admin pins it in its type's list below
 *   the index page; it's edited like any entry but not duplicated.
 * - a **person page**, `_cooks/jane`, written for one person's archive
 *   under the field. These are created on demand and never listed.
 */
final class PeoplePage
{
	/**
	 * Returns the people field an entry is its type's list page for, or
	 * `null`.
	 */
	public static function fieldOf(Entry $entry): ?PeopleField
	{
		return array_find($entry->type->people, static fn (PeopleField $field): bool => $entry->key === $field->listPage());
	}

	/**
	 * Whether an entry is one of its type's list pages.
	 */
	public static function is(Entry $entry): bool
	{
		return self::fieldOf($entry) !== null;
	}

	/**
	 * Whether an entry is a page written for a person's archive.
	 */
	public static function isPerson(Entry $entry): bool
	{
		return self::personField($entry) !== null;
	}

	/**
	 * Returns the people field an entry is a person page under, or
	 * `null`.
	 */
	public static function personField(Entry $entry): ?PeopleField
	{
		return array_find($entry->type->people, static fn (PeopleField $field): bool => dirname($entry->key) === $field->listPage());
	}

	/**
	 * Returns the slugs of a type's list pages.
	 *
	 * @return list<string>
	 */
	public static function listPages(ContentType $type): array
	{
		return array_values(array_map(static fn (PeopleField $field): string => $field->listPage(), $type->people));
	}

	/**
	 * Returns the folders a type's person pages sit in, under the content
	 * root.
	 *
	 * @return list<string>
	 */
	public static function personFolders(ContentType $type): array
	{
		return array_values(array_map(static fn (PeopleField $field): string => trim("{$type->folder}/{$field->listPage()}", '/'), $type->people));
	}
}
