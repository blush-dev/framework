<?php

/**
 * Entry places.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Blush\Content\Type\ContentTypes;

/**
 * Where entries are, from what their records say (D-656), the same for
 * every driver: an entry's key is its parents' slugs and its own, in a
 * type that keys by folder (a tree), else its slug; the folder it's
 * listed in is its type's folder and the folders of its key. A landing
 * page (slug `''`) has the key `''` and is listed in its type's folder.
 *
 * Records keep no key (D-649), so a page whose folder isn't an entry is
 * at the top of its tree until its parent is written (`content:parents`).
 * A relation archive's page in a type that doesn't nest has its place
 * in its `archive` value (`_authors/jane`), which is its key (D-657).
 */
final class EntryPlaces
{
	/**
	 * Returns each entry's key and folder, by id, in the rows' order.
	 *
	 * @param  array<string, array{type: string, slug: string, parent_id: ?string, language?: string, original_id?: ?string, archive?: ?string}> $rows By id.
	 * @return array<string, array{key: string, folder: string}>
	 */
	public static function of(array $rows, ContentTypes $types): array
	{
		$nests   = [];
		$folders = [];
		$groups  = [];

		foreach ($types->all() as $type) {
			$nests[$type->name]   = $type->keysByFolder();
			$folders[$type->name] = $type->folder;
		}

		// Each translation group's entries, by language (D-457).
		foreach ($rows as $id => $row) {
			$groups[self::group($id, $row)][$row['language'] ?? ''] = $id;
		}

		$places = [];

		foreach ($rows as $id => $row) {
			$found = self::key($id, $rows, $groups, $nests);
			$below = str_contains($found, '/') ? dirname($found) : '';

			$places[$id] = [
				'key'    => $found,
				'folder' => trim(($folders[$row['type']] ?? '') . "/{$below}", '/')
			];
		}

		return $places;
	}

	/**
	 * Returns an entry's key: its slug after its parents', each parent in
	 * the entry's language where it has a translation, else as its
	 * original is, and a translation without a parent of its own under
	 * its original's (D-457). An archive's page has its place (D-657).
	 *
	 * @param array<string, array{type: string, slug: string, parent_id: ?string, language?: string, original_id?: ?string, archive?: ?string}> $rows
	 * @param array<string, array<string, string>> $groups
	 * @param array<string, bool>                  $nests
	 */
	private static function key(string $id, array $rows, array $groups, array $nests): string
	{
		$row = $rows[$id];

		if (($row['archive'] ?? null) !== null) {
			return $row['archive'];
		}

		$language = $row['language'] ?? '';
		$parts    = [];
		$seen     = [];
		$group    = self::group($id, $row);
		$own      = $id;

		// A parent missing, or met twice, ends the walk.
		while (! isset($seen[$group])) {
			$seen[$group] = true;
			$entry        = $rows[$own];

			array_unshift($parts, $entry['slug']);

			if (! ($nests[$entry['type']] ?? false) || $entry['slug'] === '') {
				break;
			}

			$parent = $entry['parent_id'] ?? (isset($rows[$group]) ? $rows[$group]['parent_id'] : null);

			if ($parent === null || ! isset($rows[$parent])) {
				break;
			}

			$group = self::group($parent, $rows[$parent]);
			$own   = $groups[$group][$language] ?? (isset($rows[$group]) ? $group : $parent);
		}

		return implode('/', $parts);
	}

	/**
	 * Returns the translation group an entry is in: its original's id,
	 * or its own.
	 *
	 * @param array{original_id?: ?string} $row
	 */
	private static function group(string $id, array $row): string
	{
		return $row['original_id'] ?? $id;
	}
}
