<?php

/**
 * Referrers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;

/**
 * The entries linking to an entry, for what the admin says before the
 * entry leaves the site (D-598), and removing it from them before it's
 * deleted for good.
 *
 * Every relation counts but a translation's link to its original, and a
 * tree page's parent, which is its folder. Links are read from the
 * index, in every status, to the entry's original.
 */
final readonly class Referrers
{
	public function __construct(
		private ContentIndex $index,
		private ContentRepository $content,
		private Relations $relations,
		private ContentWriter $writer,
		private ContentTypes $types,
		private ClockInterface $clock
	) {}

	/**
	 * Returns the entries linking to an entry, each once, by title.
	 *
	 * @return list<Entry>
	 */
	public function of(Entry $entry): array
	{
		return array_column($this->sources($entry), 'entry');
	}

	/**
	 * Returns the entries linking to an entry, each once, by title, with
	 * the relations each links through, for warnings that say which
	 * (D-608).
	 *
	 * @return list<array{entry: Entry, relations: list<Relation>}>
	 */
	public function sources(Entry $entry): array
	{
		$found = [];

		foreach ($this->links($entry) as $link) {
			$relation = $this->relations->find($link->type, $link->relation);
			$source   = $this->content->find($link->source);

			if ($relation !== null && $source !== null) {
				$found[$source->path] ??= ['entry' => $source, 'relations' => []];
				$found[$source->path]['relations'][$relation->name] = $relation;
			}
		}

		$found = array_values(array_map(static fn (array $item): array => [...$item, 'relations' => array_values($item['relations'])], $found));

		usort($found, static fn (array $a, array $b): int => strnatcasecmp($a['entry']->title, $b['entry']->title));

		return $found;
	}

	/**
	 * Returns how many live entries credit an entry and no one else in
	 * their type's byline (D-602), so they'd show no byline without it
	 * (D-608).
	 */
	public function soleCredits(Entry $entry): int
	{
		$graph = $this->index->snapshot()->graph();
		$count = 0;

		foreach ($this->sources($entry) as ['entry' => $source, 'relations' => $relations]) {
			$byline = $this->types->byline($source->type->name);

			if (
				$byline !== null
				&& $source->id !== null
				&& $source->isPublished()
				&& array_any($relations, static fn (Relation $relation): bool => $relation->name === $byline->name)
				&& count($graph->targets($source->id, $byline->name)) === 1
			) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Returns the ids of a type's entries that live entries link to, each
	 * with how many do, for the entry list's "linked" filter (D-608).
	 * Links count as `of()` counts them.
	 *
	 * @return array<string, int>
	 */
	public function linkedIn(string $type): array
	{
		$snapshot = $this->index->snapshot();
		$now      = $this->clock->now()->getTimestamp();
		$tree     = $this->types->find($type) instanceof Tree;
		$live     = [];
		$sources  = [];

		foreach ($snapshot->graph()->all() as $link) {
			if ($link->targetType !== $type) {
				continue;
			}

			$relation = $this->relations->find($link->type, $link->relation);

			if ($relation === null || $relation->kind === RelationKind::Translation || ($tree && $relation->kind === RelationKind::Parent)) {
				continue;
			}

			$live[$link->source] ??= $snapshot->record((string) $snapshot->path($link->source))?->statusAt($now) === Status::Published;

			if ($live[$link->source]) {
				$sources[$link->target][$link->source] = true;
			}
		}

		return array_map(count(...), $sources);
	}

	/**
	 * Returns the entries linking to an entry by relation, for the editor's
	 * Linked From (D-599): each relation's key on its source type
	 * (`movie.actors`), the relation, and the entries, by title.
	 *
	 * @return list<array{key: string, type: string, relation: Relation, entries: list<Entry>}>
	 */
	public function grouped(Entry $entry): array
	{
		$groups = [];

		foreach ($this->links($entry) as $link) {
			$key      = "{$link->type}.{$link->relation}";
			$relation = $this->relations->find($link->type, $link->relation);
			$source   = $this->content->find($link->source);

			if ($relation !== null && $source !== null) {
				$groups[$key] ??= ['key' => $key, 'type' => $link->type, 'relation' => $relation, 'entries' => []];
				$groups[$key]['entries'][$source->path] = $source;
			}
		}

		ksort($groups);

		return array_values(array_map(static function (array $group): array {
			$entries = array_values($group['entries']);

			usort($entries, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

			return [...$group, 'entries' => $entries];
		}, $groups));
	}

	/**
	 * Returns how many of the entries linking to an entry are live, so the
	 * site shows the link there.
	 *
	 * @param list<Entry> $referrers As `of()` returns them.
	 */
	public static function live(array $referrers): int
	{
		return count(array_filter($referrers, static fn (Entry $entry): bool => $entry->isPublished()));
	}

	/**
	 * Removes an entry from the files linking to it, in both forms (D-589),
	 * or only from those `$allowed` passes (by entry), such as the ones an
	 * account may edit: a value naming it goes, and a relation left with
	 * none is removed. Returns the paths changed, and why each file it
	 * couldn't change was left.
	 *
	 * @param  ?Closure(Entry): bool $allowed
	 * @return array{list<string>, array<string, string>}
	 */
	public function unlink(Entry $entry, ?Closure $allowed = null): array
	{
		$target  = $this->target($entry);
		$targets = new SnapshotTargets($this->index->snapshot());
		$written = $target === null ? null : $targets->written($target);
		$changed = [];
		$failed  = [];
		$bySource = [];

		foreach ($this->links($entry) as $link) {
			$bySource[$link->source][$link->type][$link->relation] = true;
		}

		foreach ($bySource as $source => $byType) {
			$referrer = $this->content->find((string) $source);

			if ($referrer === null || ($allowed !== null && ! $allowed($referrer))) {
				continue;
			}

			try {
				$front   = $this->writer->load($referrer->path)->frontMatter;
				$changes = self::without($front, $byType, $this->relations, (string) $target, $written);

				if (! $changes->isEmpty()) {
					$this->writer->update($referrer->path, $changes);
					$changed[] = $referrer->path;
				}
			} catch (WriteException $e) {
				$failed[$referrer->path] = $e->getMessage();
			}
		}

		return [$changed, $failed];
	}

	/**
	 * Returns the changes that take a target out of a file's relations.
	 *
	 * @param array<array-key, mixed>            $front
	 * @param array<string, array<string, true>> $byType Relation names, by source type.
	 */
	private static function without(array $front, array $byType, Relations $relations, string $target, ?string $written): EntryChanges
	{
		$set    = [];
		$remove = [];
		$refs   = Refs::fromValue($front[Refs::FIELD] ?? null);

		foreach ($byType as $type => $names) {
			foreach (array_keys($names) as $name) {
				$relation = $relations->find((string) $type, (string) $name);

				if ($relation === null) {
					continue;
				}

				$key   = array_find($relation->keys(), static fn (string $key): bool => array_key_exists($key, $front)) ?? $relation->field;
				$raw   = $front[$key] ?? null;
				$items = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);
				$named = array_flip(array_keys(array_filter($refs->for($relation->name), static fn (string $id): bool => $id === $target)));
				$kept  = array_values(array_filter(
					$items,
					static function (mixed $item) use ($target, $written, $named): bool {
						$normal = LinkResolver::values($item)[0] ?? '';

						return $normal !== $target && $normal !== $written && ! isset($named[$normal]);
					}
				));

				$refs = $refs->with($relation->name, array_filter($refs->for($relation->name), static fn (string $id): bool => $id !== $target));

				if ($kept === []) {
					$remove[] = $key;
				} elseif (count($kept) !== count($items)) {
					$set[$key] = is_array($raw) ? $kept : $kept[0];
				}
			}
		}

		if ($refs->isEmpty()) {
			if (array_key_exists(Refs::FIELD, $front)) {
				$remove[] = Refs::FIELD;
			}
		} elseif ($refs->toArray() !== ($front[Refs::FIELD] ?? null)) {
			$set[Refs::FIELD] = $refs->toArray();
		}

		return new EntryChanges($set, array_values(array_unique($remove)));
	}

	/**
	 * Returns the links to an entry's original that count.
	 *
	 * @return list<Link>
	 */
	private function links(Entry $entry): array
	{
		$target = $this->target($entry);

		if ($target === null) {
			return [];
		}

		$tree = $entry->type instanceof Tree;

		return array_values(array_filter(
			$this->index->snapshot()->graph()->linksTo($target),
			fn (Link $link): bool => ($relation = $this->relations->find($link->type, $link->relation)) !== null
				&& $relation->kind !== RelationKind::Translation
				&& ! ($tree && $relation->kind === RelationKind::Parent)
		));
	}

	/**
	 * Returns the id of an entry's original, or `null` for one without an
	 * id.
	 */
	private function target(Entry $entry): ?string
	{
		return $entry->id === null ? null : new SnapshotTargets($this->index->snapshot())->original($entry->id) ?? $entry->id;
	}
}
