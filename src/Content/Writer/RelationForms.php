<?php

/**
 * Relation forms.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\Refs;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationProblem;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\Resolution;

/**
 * Turns an entry's relations, as Blush would file them (D-589), into the
 * changes that file them (D-596): each relation's ids under `refs.{name}`,
 * and its written form under the key the file already uses (else the
 * relation's own).
 *
 * A value naming nothing (a term not written yet, titled as it was
 * typed, `Book Reviews`) is kept as the file spells it, so nothing is
 * lost, and only the ids of the values that do link are filed. A tree
 * page's parent is written by its folder, so only its id is filed. One
 * value written on its own (`author: jane`) stays on its own, though the
 * relation takes a list.
 */
final readonly class RelationForms
{
	/**
	 * Returns the changes, empty when the file already says the same.
	 *
	 * @param array<array-key, mixed>   $frontMatter The file's front matter.
	 * @param array<string, Resolution> $stale       Resolutions to file, by relation name.
	 * @param bool                      $tree        Whether the entry is a tree's page.
	 */
	public static function changes(array $frontMatter, array $stale, Relations $relations, string $type, bool $tree): EntryChanges
	{
		$set  = [];
		$refs = Refs::fromValue($frontMatter[Refs::FIELD] ?? null);

		foreach ($stale as $name => $resolution) {
			$relation = $relations->find($type, $name);

			if ($relation === null) {
				continue;
			}

			$refs = $refs->with($name, $resolution->refs);

			if ($tree && $relation->kind === RelationKind::Parent) {
				continue;
			}

			$key     = array_find($relation->keys(), static fn (string $key): bool => array_key_exists($key, $frontMatter)) ?? $relation->field;
			$raw     = $frontMatter[$key] ?? null;
			$written = self::spelled($resolution, $raw);
			$value   = $relation->multiple && (is_array($raw) || count($written) !== 1) ? $written : $written[0] ?? null;

			if ($raw !== $value) {
				$set[$key] = $value;
			}
		}

		$remove = [];

		if ($refs->isEmpty()) {
			$remove = array_key_exists(Refs::FIELD, $frontMatter) ? [Refs::FIELD] : [];
		} elseif ($refs->toArray() !== ($frontMatter[Refs::FIELD] ?? null)) {
			$set[Refs::FIELD] = $refs->toArray();
		}

		return new EntryChanges($set, $remove);
	}

	/**
	 * Returns a resolution's written form, with each value that names
	 * nothing spelled as the file wrote it.
	 *
	 * @return list<string>
	 */
	private static function spelled(Resolution $resolution, mixed $raw): array
	{
		if ($resolution->problems === []) {
			return $resolution->written;
		}

		$unlinked = array_flip(array_map(static fn (RelationProblem $problem): string => $problem->value, $resolution->problems));
		$spelling = [];

		foreach (is_array($raw) ? $raw : [$raw] as $item) {
			$normal = LinkResolver::values($item)[0] ?? null;

			if ($normal !== null && (is_string($item) || is_int($item))) {
				$spelling[$normal] ??= (string) $item;
			}
		}

		return array_map(
			static fn (string $written): string => isset($unlinked[$written]) ? $spelling[$written] ?? $written : $written,
			$resolution->written
		);
	}
}
