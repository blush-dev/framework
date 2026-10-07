<?php

/**
 * Link resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Support\Slug;
use Blush\Support\Uuid;

/**
 * Turns an entry's two forms of a relation (D-589) into links: the
 * written values (in order) and the ids `refs` keeps for them.
 *
 * - **A written value that's an id** links to that entry, and is written
 *   back as its slug.
 * - **A written value with an id in `refs`** links to that id's entry
 *   when it's one of the relation's types, so a target renamed or moved
 *   since keeps its link, and the value is written back as its new slug.
 * - **A written value without one** is looked up by slug (or a tree's
 *   path) in each target type, and its id is filled in.
 * - **A value that finds nothing** links to nothing and is reported; it
 *   stays in the written form, so a term not written yet isn't lost.
 * - **`refs` entries for values no longer written** are dropped, since
 *   the id form is made from the links.
 *
 * An entry never links to itself, and a target named twice links once.
 * Bounds (`min`, `max`) are checked over the whole index
 * (`RelationChecker`), not here.
 */
final readonly class LinkResolver
{
	public function __construct(private TargetLookup $targets)
	{}

	/**
	 * Resolves one entry's values in one relation.
	 *
	 * @param mixed  $value    The front matter value: one written value or a list.
	 * @param string $source   The entry's id.
	 * @param string $type     The entry's type.
	 * @param string $language The entry's language, where slugs are looked up first.
	 */
	public function resolve(Relation $relation, mixed $value, Refs $refs, string $source, string $type, string $language): Resolution
	{
		$values   = self::values($value);
		$key      = $relation->key($type);
		$links    = [];
		$written  = [];
		$ids      = [];
		$problems = [];

		foreach ($values as $item) {
			[$id, $problem] = $this->target($relation, $item, $refs, $key, $source, $language);

			if ($problem !== null) {
				$problems[] = $problem;
				$written[]  = $item;
				continue;
			}

			if ($id === null || isset($ids[$id])) {
				continue;
			}

			$targetType = (string) $this->targets->typeOf($id);
			$slug       = $this->targets->written($id) ?? $item;

			$links[]        = new Link($source, $type, $relation->name, $id, $targetType, count($links));
			$written[]      = $slug;
			$ids[$id]       = $slug;
		}

		$map = array_flip($ids);

		return new Resolution(
			$links,
			$written,
			$map,
			$problems,
			$written !== $values || $map !== $refs->for($relation->name)
		);
	}

	/**
	 * Returns the target id a written value links to, or a problem.
	 *
	 * @return array{?string, ?RelationProblem}
	 */
	private function target(Relation $relation, string $item, Refs $refs, string $key, string $source, string $language): array
	{
		$problem = static fn (ProblemKind $kind, string $message): array => [null, new RelationProblem($kind, $key, $source, $item, $message)];

		if (Uuid::isValid($item)) {
			$id   = $this->targets->original(strtolower($item));
			$type = $id === null ? null : $this->targets->typeOf($id);

			return match (true) {
				$type === null              => $problem(ProblemKind::Missing, sprintf('"%s" names no entry\'s id.', $item)),
				! $relation->isTo($type)    => $problem(ProblemKind::WrongType, sprintf('"%s" names a %s, which "%s" doesn\'t point to.', $item, $type, $relation->name)),
				$id === $source             => $problem(ProblemKind::SelfLink, 'An entry can\'t link to itself.'),
				default                     => [$id, null]
			};
		}

		$filed = $refs->idFor($relation->name, $item);
		$filed = $filed === null ? null : $this->targets->original($filed);

		if ($filed !== null && $relation->isTo((string) $this->targets->typeOf($filed))) {
			return $filed === $source ? $problem(ProblemKind::SelfLink, 'An entry can\'t link to itself.') : [$filed, null];
		}

		$found = [];

		foreach ($relation->to as $type) {
			$id = $this->targets->find($type, $item, $language);

			if ($id !== null) {
				$found[$type] = $id;
			}
		}

		return match (true) {
			$found === []               => $problem(ProblemKind::Missing, sprintf('"%s" names no %s.', $item, implode(' or ', $relation->to))),
			count($found) > 1           => $problem(ProblemKind::Ambiguous, sprintf('"%s" names a %s; write the id to say which.', $item, implode(' and a ', array_keys($found)))),
			array_first($found) === $source => $problem(ProblemKind::SelfLink, 'An entry can\'t link to itself.'),
			default                     => [array_first($found), null]
		};
	}

	/**
	 * Returns the written values in a front matter value, as the
	 * reference field normalizes them: each a slug (a tree's path keeps
	 * its slashes), an id kept as written.
	 *
	 * @return list<string>
	 */
	private static function values(mixed $value): array
	{
		$values = [];

		foreach (is_array($value) ? $value : [$value] as $item) {
			if (! is_string($item) && ! is_int($item)) {
				continue;
			}

			$item = Uuid::isValid((string) $item)
				? strtolower((string) $item)
				: implode('/', array_filter(array_map(Slug::from(...), explode('/', (string) $item)), static fn (string $part): bool => $part !== ''));

			if ($item !== '' && ! in_array($item, $values, true)) {
				$values[] = $item;
			}
		}

		return $values;
	}
}
