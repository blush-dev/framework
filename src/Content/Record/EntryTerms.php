<?php

/**
 * Entry terms.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Blush\Content\Relation\RelationKind;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;

/**
 * The slugs an entry refers to, by term key (`category`,
 * `profile.authors`), as route placeholders read them: each relation
 * from the entry's type with a term key, read from what the entry links
 * to (D-803) in its order, and a credit relation's under its profiles
 * type too (D-602). The same for every driver (D-662): terms follow
 * their targets through a rename, and a value that links nothing (a
 * term without an entry, D-584) isn't a term.
 */
final class EntryTerms
{
	/**
	 * Returns what a type's terms are read from: each relation's term
	 * key, the type of its terms, its field, its aliases, whether it's a
	 * credit relation, and its name.
	 *
	 * @return list<array{string, string, string, list<string>, bool, string}>
	 */
	public static function sources(ContentTypes $types, ContentType $type): array
	{
		$sources = [];

		foreach ($types->relations() as $relation) {
			$key = $relation->termKey();

			if ($key !== null && $relation->isFrom($type->name)) {
				$sources[] = [$key, $relation->to[0], $relation->field, $relation->aliases, $relation->kind === RelationKind::Credit, $relation->name];
			}
		}

		return $sources;
	}

	/**
	 * Returns the slugs an entry links to, by term key.
	 *
	 * @param  list<array{string, string, string, list<string>, bool, string}> $sources From `sources()`.
	 * @param  array<string, array<string, string>>                           $links   Target slugs by id, by relation, in order (`Entry::links()`).
	 * @return array<string, list<string>>
	 */
	public static function of(array $sources, array $links): array
	{
		$terms = [];

		foreach ($sources as [$key, $labelKey, , , $together, $relation]) {
			$slugs = array_values($links[$relation] ?? []);

			if ($slugs === []) {
				continue;
			}

			$terms[$key] = $slugs;

			if ($together) {
				$terms[$labelKey] = array_values(array_unique([...$terms[$labelKey] ?? [], ...$slugs]));
			}
		}

		return $terms;
	}

	/**
	 * Returns the term slugs front matter names as written, by term key:
	 * for the filesystem driver's index, which finds the terms files name
	 * before their refs are worked out.
	 *
	 * @param  list<array{string, string, string, list<string>, bool, string}> $sources From `sources()`.
	 * @param  array<string, mixed>                                            $values  Normalized front matter.
	 * @return array<string, list<string>>
	 */
	public static function written(array $sources, array $values): array
	{
		$terms = [];

		foreach ($sources as [$key, $labelKey, $field, , $together]) {
			$value = $values[$field] ?? [];
			$slugs = [];

			foreach (is_array($value) ? $value : [$value] as $slug) {
				if (is_scalar($slug) && $slug !== '') {
					$slugs[] = (string) $slug;
				}
			}

			if ($slugs === []) {
				continue;
			}

			$terms[$key] = $slugs;

			if ($together) {
				$terms[$labelKey] = array_values(array_unique([...$terms[$labelKey] ?? [], ...$slugs]));
			}
		}

		return $terms;
	}
}
