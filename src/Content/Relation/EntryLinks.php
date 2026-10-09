<?php

/**
 * Entry links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\Record\EntryRecords;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;

/**
 * The links between entries (D-585), as the record layer keeps them
 * (D-649, D-654): each ref, and each entry's parent (its `parent_id`)
 * through its type's parent relation, as `Link`s with the types at both
 * ends. A translation's link to its original is its `original_id`, read
 * through `TargetLookup::original()`, not here.
 *
 * Links run between originals' ids (D-587); a relation is named on its
 * source's type (`actors`) or with the type (`movie.actors`).
 */
final readonly class EntryLinks
{
	public function __construct(
		private EntryRecords $records,
		private Relations $relations
	) {}

	/**
	 * Returns a source's links, in order: in one relation by name, or in
	 * every one.
	 *
	 * @return list<Link>
	 */
	public function links(string $source, ?string $relation = null): array
	{
		$source = strtolower($source);
		$query  = $this->records->refs()->where('source_id', Operator::Equal, $source);
		$query  = $relation === null ? $query : $query->where('relation', Operator::Equal, $relation);
		$links  = $this->hydrate($query);

		usort($links, static fn (Link $a, Link $b): int => [$a->relation, $a->position] <=> [$b->relation, $b->position]);

		$entry  = $this->records->find($source);
		$parent = $entry === null ? null : $this->parentLink($entry);

		return $parent !== null && ($relation === null || $relation === $parent->relation) ? [$parent, ...$links] : $links;
	}

	/**
	 * Returns the ids a source links to in a relation, in order.
	 *
	 * @return list<string>
	 */
	public function targets(string $source, string $relation): array
	{
		return array_map(static fn (Link $link): string => $link->target, $this->links($source, $relation));
	}

	/**
	 * Returns the ids each of several sources links to in a relation, in
	 * order, by source, in one read.
	 *
	 * @param  list<string>                $sources
	 * @return array<string, list<string>>
	 */
	public function targetsOf(array $sources, string $relation): array
	{
		if ($sources === []) {
			return [];
		}

		$sources = array_values(array_unique(array_map(strtolower(...), $sources)));
		$found   = [];
		$refs    = $this->records->refs()
			->where('source_id', Operator::In, $sources)
			->where('relation', Operator::Equal, $relation)
			->orderBy('position')
			->get();

		foreach ($refs as $ref) {
			$found[EntryRecords::text($ref, 'source_id')][] = EntryRecords::text($ref, 'target_id');
		}

		foreach ($this->records->findMany($sources) as $entry) {
			$parent = $this->parentLink($entry);

			if ($parent !== null && $parent->relation === $relation) {
				$found[$entry->id] = [$parent->target, ...$found[$entry->id] ?? []];
			}
		}

		return $found;
	}

	/**
	 * Returns the links pointing at a target: through one relation's key
	 * (`movie.actors`), one relation by name on any type (`actors`), or
	 * every relation.
	 *
	 * @return list<Link>
	 */
	public function linksTo(string $target, ?string $relation = null): array
	{
		$target = strtolower($target);
		$name   = $relation === null ? null : self::nameOf($relation);
		$query  = $this->records->refs()->where('target_id', Operator::Equal, $target);
		$query  = $name === null ? $query : $query->where('relation', Operator::Equal, $name);
		$links  = $this->hydrate($query);

		foreach ($this->records->entries()->where('parent_id', Operator::Equal, $target)->get() as $child) {
			$link = $this->parentLink($child);

			if ($link !== null) {
				$links[] = $link;
			}
		}

		return array_values(array_filter($links, static fn (Link $link): bool => $relation === null || $link->relation === $relation || $link->key() === $relation));
	}

	/**
	 * Returns the ids of the sources pointing at a target, as `linksTo()`
	 * finds them, without repeats.
	 *
	 * @return list<string>
	 */
	public function sources(string $target, ?string $relation = null): array
	{
		return array_values(array_unique(array_map(static fn (Link $link): string => $link->source, $this->linksTo($target, $relation))));
	}

	/**
	 * Returns the links in one relation by name, on any type, or every
	 * link.
	 *
	 * @return list<Link>
	 */
	public function all(?string $relation = null): array
	{
		$query = $this->records->refs();
		$links = $this->hydrate($relation === null ? $query : $query->where('relation', Operator::Equal, $relation));

		foreach ($this->records->entries()->where('parent_id', Operator::NotNull)->get() as $child) {
			$link = $this->parentLink($child);

			if ($link !== null && ($relation === null || $link->relation === $relation)) {
				$links[] = $link;
			}
		}

		return $links;
	}

	/**
	 * Returns the refs a query finds as links, with their ends' types; a
	 * ref whose source or target isn't an entry is left out.
	 *
	 * @return list<Link>
	 */
	private function hydrate(RecordQuery $query): array
	{
		$refs  = $query->get()->records;
		$ids   = [];

		foreach ($refs as $ref) {
			$ids[] = EntryRecords::text($ref, 'source_id');
			$ids[] = EntryRecords::text($ref, 'target_id');
		}

		$types = array_map(static fn (Record $record): string => EntryRecords::text($record, 'type'), $this->records->findMany(array_values(array_unique($ids))));
		$links = [];

		foreach ($refs as $ref) {
			$source = EntryRecords::text($ref, 'source_id');
			$target = EntryRecords::text($ref, 'target_id');

			if (isset($types[$source], $types[$target])) {
				$position = $ref->fields['position'] ?? 0;
				$links[]  = new Link($source, $types[$source], EntryRecords::text($ref, 'relation'), $target, $types[$target], is_int($position) ? $position : 0);
			}
		}

		return $links;
	}

	/**
	 * Returns the link an entry has to its parent through its type's
	 * parent relation, or `null` without either.
	 */
	private function parentLink(Record $entry): ?Link
	{
		$parent = $entry->fields['parent_id'] ?? null;

		if (! is_string($parent)) {
			return null;
		}

		$type     = EntryRecords::text($entry, 'type');
		$relation = array_find($this->relations->for($type), static fn (Relation $relation): bool => $relation->kind === RelationKind::Parent);

		return $relation === null ? null : new Link($entry->id, $type, $relation->name, $parent, $type);
	}

	/**
	 * Returns a relation's name from its key (`movie.actors`) or name.
	 */
	private static function nameOf(string $relation): string
	{
		$dot = strrpos($relation, '.');

		return $dot === false ? $relation : substr($relation, $dot + 1);
	}
}
