<?php

/**
 * Relation limits.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\Index\ContentIndex;
use Blush\Content\Status;

/**
 * Checks one entry's relations against their limits before it's
 * published (D-596), as `RelationChecker` checks the whole index for
 * `content:lint`: at least a relation's `min` values, at most its `max`,
 * and no target named by more entries than the inverse's `max`, counting
 * this one and drafts but not the trash (D-598). Drafts may have fewer (D-585).
 *
 * Values are counted as written, so a value naming nothing yet (a term
 * created as it's typed) counts. A parent and a translation are one
 * target by their nature and aren't checked.
 */
final readonly class RelationLimits
{
	public function __construct(
		private Relations $relations,
		private ContentIndex $index
	) {}

	/**
	 * Returns what's out of bounds, or nothing.
	 *
	 * @param  array<array-key, mixed> $frontMatter The entry's front matter as it would be saved.
	 * @param  ?string                 $id          The entry's id, or `null` for a new one.
	 * @return list<RelationProblem>
	 */
	public function check(string $type, ?string $id, string $language, array $frontMatter): array
	{
		$snapshot = $this->index->snapshot();
		$problems = [];
		$refs     = Refs::fromValue($frontMatter[Refs::FIELD] ?? null);
		$source   = $id ?? '';

		foreach ($this->relations->for($type) as $relation) {
			if ($relation->kind->isWithinType()) {
				continue;
			}

			$key   = $relation->key($type);
			$value = $relation->valueIn($frontMatter);
			$count = count(LinkResolver::values($value));
			$noun  = $relation->label === '' ? $relation->field : $relation->label;

			if ($count < $relation->min) {
				$problems[] = new RelationProblem(ProblemKind::TooFew, $key, $source, '', sprintf('%s needs at least %d to publish; it has %d.', ucfirst($noun), $relation->min, $count));
			}

			if ($relation->max !== null && $count > $relation->max) {
				$problems[] = new RelationProblem(ProblemKind::TooMany, $key, $source, '', sprintf('%s takes at most %d; it has %d.', ucfirst($noun), $relation->max, $count));
			}

			$max = $relation->inverse === false ? null : $relation->inverse->max;

			if ($max === null || $count === 0) {
				continue;
			}

			$graph = $snapshot->graph();

			foreach (new LinkResolver(new SnapshotTargets($snapshot))->resolve($relation, $value, $refs, $source, $type, $language)->links as $link) {
				$others = array_filter(
					array_diff($graph->sources($link->target, $relation->name), [$source]),
					static fn (string $id): bool => ($snapshot->records[(string) $snapshot->path($id)]['status'] ?? null) !== Status::Trash->value
				);

				if (count($others) < $max) {
					continue;
				}

				$target = $snapshot->record((string) $snapshot->path($link->target));

				$problems[] = new RelationProblem(ProblemKind::InverseLimit, $key, $source, $target->slug ?? $link->target, sprintf(
					'"%s" already has the most entries naming it in %s (%d).',
					$target->title ?? $link->target,
					$noun,
					$max
				));
			}
		}

		return $problems;
	}
}
