<?php

/**
 * Relation checker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Checks the rules that hold over the whole graph rather than one
 * entry's values (D-585, D-587):
 *
 * - a live entry has at least a relation's `min` targets (drafts may
 *   have fewer), and no entry more than its `max`;
 * - a hierarchical relation has no cycles;
 * - no target has more sources than the inverse's `max`.
 *
 * "Live" is `published` until the status API exists (D-588); the caller
 * says which entries are.
 */
final readonly class RelationChecker
{
	/**
	 * Returns what's wrong.
	 *
	 * @param  array<string, string> $entries Every entry with an id, its type by id.
	 * @param  list<string>          $live    The ids of the live ones.
	 * @param  list<string>          $trashed The ids of those in the trash, which an inverse's `max` doesn't count (D-598).
	 * @return list<RelationProblem>
	 */
	public function check(Relations $relations, RelationGraph $graph, array $entries, array $live, array $trashed = []): array
	{
		$problems = [];
		$live     = array_flip($live);
		$trashed  = array_flip($trashed);

		foreach ($entries as $id => $type) {
			foreach ($relations->for($type) as $relation) {
				$count = count($graph->targets($id, $relation->name));
				$key   = $relation->key($type);

				if (isset($live[$id]) && $count < $relation->min) {
					$problems[] = new RelationProblem(ProblemKind::TooFew, $key, $id, '', sprintf('Needs at least %d %s to be published; it has %d.', $relation->min, $relation->name, $count));
				}

				if ($relation->max !== null && $count > $relation->max) {
					$problems[] = new RelationProblem(ProblemKind::TooMany, $key, $id, '', sprintf('Takes at most %d %s; it has %d.', $relation->max, $relation->name, $count));
				}

				if ($relation->isHierarchical() && $this->cycles($graph, $id, $relation->name)) {
					$problems[] = new RelationProblem(ProblemKind::Cycle, $key, $id, '', sprintf('Its %s leads back to itself.', $relation->name));
				}
			}
		}

		foreach ($relations->relations as $relation) {
			$max = $relation->inverse === false ? null : $relation->inverse->max;

			if ($max === null) {
				continue;
			}

			foreach ($entries as $id => $type) {
				$sources = array_filter($graph->linksTo($id, $relation->name), static fn (Link $link): bool => $relation->isFrom($link->type) && ! isset($trashed[$link->source]));

				if (count($sources) > $max) {
					$problems[] = new RelationProblem(ProblemKind::InverseLimit, $relation->name, $id, '', sprintf('At most %d may name it in %s; %d do.', $max, $relation->name, count($sources)));
				}
			}
		}

		return $problems;
	}

	/**
	 * Returns whether following a relation from an entry comes back to
	 * it.
	 */
	private function cycles(RelationGraph $graph, string $start, string $relation): bool
	{
		$seen    = [];
		$pending = $graph->targets($start, $relation);

		while ($pending !== []) {
			$id = array_shift($pending);

			if ($id === $start) {
				return true;
			}

			if (isset($seen[$id])) {
				continue;
			}

			$seen[$id] = true;
			$pending   = [...$pending, ...$graph->targets($id, $relation)];
		}

		return false;
	}
}
