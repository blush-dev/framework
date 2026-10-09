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

use Blush\Content\Relation\Refs;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;

/**
 * The term slugs an entry's front matter names, by term key (`category`,
 * `profile.authors`), as `Entry::terms()` gives them: each relation from
 * the entry's type with a term key, read from its field, and a credit
 * relation's under its profiles type too (D-602). A written value whose
 * id is filed under `refs` (D-589), or that is an id, reads as its
 * target's slug now, so
 * terms follow an id through a rename; one that links nothing (a term
 * without an entry yet) is kept as written (D-584).
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
	 * Returns the term slugs normalized front matter names, by term key,
	 * each written value filed with an id read as that id's slug.
	 *
	 * @param  list<array{string, string, string, list<string>, bool, string}> $sources From `sources()`.
	 * @param  array<string, mixed>                                            $values
	 * @param  array<string, string>                                           $targets Targets' slugs, by id.
	 * @return array<string, list<string>>
	 */
	public static function of(array $sources, array $values, ?Refs $refs = null, array $targets = []): array
	{
		$terms = [];

		foreach ($sources as [$key, $labelKey, $field, , $together, $relation]) {
			$value = $values[$field] ?? [];
			$slugs = [];

			// Plain loops: every entry built reads its terms.
			foreach (is_array($value) ? $value : [$value] as $slug) {
				if (! is_scalar($slug) || $slug === '') {
					continue;
				}

				$slug    = (string) $slug;
				$slugs[] = $targets[$refs?->idFor($relation, $slug) ?? strtolower($slug)] ?? $slug;
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
