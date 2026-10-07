<?php

/**
 * Link builder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;

/**
 * Builds every link from the content index (D-585), for the relation
 * graph. Each entry with an id is a source:
 *
 * - **A relation written in front matter** is read from its key or an
 *   alias (normalized by its field when the type declares one, else as
 *   written), with the entry's `refs`, through `LinkResolver`.
 * - **A parent** is read like any relation, from `parent` and `refs`
 *   (D-591), except in a tree, whose folder is its written form: the
 *   folder always wins, since a page's parent is renamed or moved only
 *   with the pages under it, so an id in `refs` naming another entry is
 *   one a page moved by hand left behind, and it's replaced.
 * - **A translation** links to its original.
 *
 * An entry without an id links nothing and is linked by nothing: a link
 * is between ids (`content:ids` gives them one).
 *
 * It also gives each entry's terms and credits in the shape the index
 * has always kept them (`IndexRecord::$terms`), from each relation's
 * written form as Blush would write it: a classify relation's slugs
 * under its name, a credit relation's under `{profiles}.{field}` and
 * together under `{profiles}`. So `terms()`, `whereTerm()`, and
 * `termCounts()` follow an id through a rename, and a value that links
 * to nothing yet (a term without a file) is still kept (D-584).
 */
final readonly class LinkBuilder
{
	/**
	 * Builds the links.
	 */
	public function build(IndexSnapshot $snapshot, Relations $relations, ContentTypes $types): LinkReport
	{
		$targets  = new SnapshotTargets($snapshot);
		$resolver = new LinkResolver($targets);
		$links    = [];
		$problems = [];
		$stale    = [];
		$terms    = [];

		foreach ($snapshot->records as $path => $record) {
			$id = $record['id'];

			if ($id === null) {
				continue;
			}

			$front        = [...$record['extra'], ...$record['values']];
			$terms[$path] = [];
			$refs  = Refs::fromValue($front[Refs::FIELD] ?? null);

			foreach ($relations->for($record['type']) as $relation) {
				if ($relation->kind === RelationKind::Translation) {
					$target = $record['original'] === null ? null : $targets->original($id);

					if ($target !== null && $target !== $id) {
						$links[] = new Link($id, $record['type'], $relation->name, $target, $record['type']);
					}

					continue;
				}

				$resolution = $relation->kind === RelationKind::Parent && $types->find($record['type']) instanceof Tree
					? self::byFolder($resolver, $relation, $record['parent'], $refs, $id, $record['type'], $record['language'])
					: self::byKey($resolver, $relation, $front, $refs, $id, $record['type'], $record['language']);

				if ($resolution === null) {
					continue;
				}

				$links        = [...$links, ...$resolution->links];
				$problems     = [...$problems, ...$resolution->problems];
				$terms[$path] = self::terms($terms[$path], $relation, $resolution->written);

				if ($resolution->changed) {
					$stale[$path][$relation->name] = $resolution;
				}
			}
		}

		return new LinkReport(RelationGraph::build($links), $problems, $stale, $terms);
	}

	/**
	 * Returns an entry's terms with a relation's written form added, when
	 * it's a classify or credit relation and has any.
	 *
	 * @param  array<string, list<string>> $terms
	 * @param  list<string>                $written
	 * @return array<string, list<string>>
	 */
	private static function terms(array $terms, Relation $relation, array $written): array
	{
		if ($written === []) {
			return $terms;
		}

		if ($relation->kind === RelationKind::Classify) {
			$terms[$relation->name] = $written;
		} elseif ($relation->kind === RelationKind::Credit) {
			$profiles = $relation->to[0];

			$terms["{$profiles}.{$relation->name}"] = $written;
			$terms[$profiles]                       = array_values(array_unique([...$terms[$profiles] ?? [], ...$written]));
		}

		return $terms;
	}

	/**
	 * Resolves a relation written under its key or an alias, or returns
	 * `null` when the file has neither a value nor ids for it.
	 *
	 * @param array<array-key, mixed> $front The entry's front matter.
	 */
	private static function byKey(LinkResolver $resolver, Relation $relation, array $front, Refs $refs, string $id, string $type, string $language): ?Resolution
	{
		$value = array_find(
			array_map(static fn (string $key): mixed => $front[$key] ?? null, $relation->keys()),
			static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
		);

		return $value === null && $refs->for($relation->name) === []
			? null
			: $resolver->resolve($relation, $value, $refs, $id, $type, $language);
	}

	/**
	 * Resolves a tree page's parent from its folder (D-591), which wins
	 * over the id `refs` keeps for it: the ids are found again, and the
	 * resolution is stale only when they differ from the file's.
	 */
	private static function byFolder(LinkResolver $resolver, Relation $relation, ?string $parent, Refs $refs, string $id, string $type, string $language): Resolution
	{
		$found = $resolver->resolve($relation, $parent, $refs->with($relation->name, []), $id, $type, $language);

		return new Resolution($found->links, $found->written, $found->refs, $found->problems, $found->refs !== $refs->for($relation->name));
	}
}
