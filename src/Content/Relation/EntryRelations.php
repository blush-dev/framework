<?php

/**
 * Entry relations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Index\ContentIndex;

/**
 * Reads relations for the site (D-585): an entry's targets, and the
 * entries that point at one, as entries.
 *
 * - **Only live entries** come back, at either end: `published` until
 *   the status API exists (D-588).
 * - **In the reader's language** (D-587): a target with a translation in
 *   the entry's language comes back as that translation, else as its
 *   original. A translation's own links relate to its original's by the
 *   relation's `translations` rule.
 * - **Symmetric relations** answer from both ends: an entry's related
 *   posts include the ones naming it.
 *
 * Relations are named on the entry's type (`related($movie, 'actors')`);
 * a reverse lookup takes a key on a source type (`movie.actors`) or a
 * name on any type (`actors`).
 */
final readonly class EntryRelations
{
	public function __construct(
		private Relations $relations,
		private ContentIndex $index,
		private ContentRepository $content
	) {}

	/**
	 * Returns the live entries an entry links to in a relation, in order,
	 * in a language (the entry's by default).
	 *
	 * @return list<Entry>
	 */
	public function related(Entry $entry, string $relation, ?string $language = null): array
	{
		$definition = $this->relations->find($entry->type->name, $relation);

		if ($definition === null || $entry->id === null) {
			return [];
		}

		$ids = $this->graph()->targets($entry->id, $relation);

		if ($definition->symmetric) {
			$ids = array_values(array_unique([...$ids, ...$this->graph()->sources($entry->id, $definition->key($entry->type->name))]));
		}

		$original = $this->targets()->original($entry->id);

		if ($original !== null && $original !== $entry->id) {
			$ids = $definition->translations->apply($ids, $this->graph()->targets($original, $relation));
		}

		return $this->entries($ids, $language ?? $entry->language);
	}

	/**
	 * Returns an entry's links in a relation, as records, in order:
	 * what's stored, before languages, statuses, and symmetry.
	 *
	 * @return list<Link>
	 */
	public function links(Entry $entry, string $relation): array
	{
		return $entry->id === null ? [] : $this->graph()->links($entry->id, $relation);
	}

	/**
	 * Returns the live entries pointing at an entry through a relation:
	 * a key on a source type (`movie.actors`), or a name on any type.
	 * They're in a language (the entry's by default; a term with no
	 * translation is in its original's) when they have a translation in it,
	 * newest published first, then by title (as collections are, D-516;
	 * an order of its own for the reverse side is open).
	 *
	 * Each source is seen as `related()` sees it: an original's link
	 * shows as its translation in the entry's language only when the
	 * relation's `translations` rule keeps it there, and a translation's
	 * own link shows only in its language.
	 *
	 * @return list<Entry>
	 */
	public function referencedBy(Entry $entry, string $relation, ?string $language = null): array
	{
		$target = $entry->id === null ? null : $this->targets()->original($entry->id);

		if ($target === null) {
			return [];
		}

		$entries = [];

		foreach ($this->graph()->linksTo($target, $relation) as $link) {
			$shown = $this->source($link, $target, $language ?? $entry->language);

			if ($shown !== null && $shown->isPublished()) {
				$entries[$shown->path] = $shown;
			}
		}

		$entries = array_values($entries);

		usort($entries, static fn (Entry $a, Entry $b): int => [$b->published?->getTimestamp(), $a->title] <=> [$a->published?->getTimestamp(), $b->title]);

		return $entries;
	}

	/**
	 * Returns the entry a link pointing at a target shows as in a
	 * language, or `null` when it doesn't show there.
	 */
	private function source(Link $link, string $target, string $language): ?Entry
	{
		$source     = $this->content->find($link->source);
		$definition = $this->relations->find($link->type, $link->relation);

		if ($source === null || $definition === null) {
			return null;
		}

		// A translation's own link shows only in its own language.
		if ($this->targets()->original($link->source) !== $link->source) {
			return $source->language === $language ? $source : null;
		}

		$translation = $this->content->translation($source, $language);

		if ($translation === null || $translation === $source || ! $translation->isPublished() || $translation->id === null) {
			return $source;
		}

		$shown = $definition->translations->apply(
			$this->graph()->targets($translation->id, $link->relation),
			$this->graph()->targets($link->source, $link->relation)
		);

		return in_array($target, $shown, true) ? $translation : null;
	}

	/**
	 * Returns the live entries with ids, each in a language when it has
	 * a translation in it, without repeats.
	 *
	 * @param  list<string> $ids
	 * @return list<Entry>
	 */
	private function entries(array $ids, string $language): array
	{
		$entries = [];

		foreach ($ids as $id) {
			$original = $this->content->find($id);
			$entry    = $original === null ? null : $this->content->translation($original, $language);
			$entry    = $entry !== null && $entry->isPublished() ? $entry : $original;

			if ($entry !== null && $entry->isPublished() && ! isset($entries[$entry->path])) {
				$entries[$entry->path] = $entry;
			}
		}

		return array_values($entries);
	}

	/**
	 * Returns the current index's relation graph.
	 */
	private function graph(): RelationGraph
	{
		return $this->index->snapshot()->graph();
	}

	/**
	 * Returns the current index's targets.
	 */
	private function targets(): TargetLookup
	{
		return new SnapshotTargets($this->index->snapshot());
	}
}
