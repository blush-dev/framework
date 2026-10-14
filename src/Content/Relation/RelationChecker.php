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

use Blush\Translation\Messages;

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
	 * @param Messages $messages Writes what's wrong.
	 */
	public function __construct(private Messages $messages)
	{}

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
					$problems[] = new RelationProblem(ProblemKind::TooFew, $key, $id, '', $this->messages->t('content.relation_checker.too_few', 'Needs at least {min} {relation} to be published; it has {count}.', min: $relation->min, relation: $relation->name, count: $count));
				}

				if ($relation->max !== null && $count > $relation->max) {
					$problems[] = new RelationProblem(ProblemKind::TooMany, $key, $id, '', $this->messages->t('content.relation_checker.too_many', 'Takes at most {max} {relation}; it has {count}.', max: $relation->max, relation: $relation->name, count: $count));
				}

				if ($relation->isHierarchical() && $this->cycles($graph, $id, $relation->name)) {
					$problems[] = new RelationProblem(ProblemKind::Cycle, $key, $id, '', $this->messages->t('content.relation_checker.cycle', 'Its {relation} leads back to itself.', relation: $relation->name));
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
					$problems[] = new RelationProblem(ProblemKind::InverseLimit, $relation->name, $id, '', $this->messages->t('content.relation_checker.inverse_over', 'At most {max} may name it in {relation}; {count} do.', max: $max, relation: $relation->name, count: count($sources)));
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
