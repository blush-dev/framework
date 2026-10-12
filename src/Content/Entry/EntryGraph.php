<?php

/**
 * Entry graph.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use Blush\Content\Entries;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Relation\Relation;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaException;
use Blush\Media\MediaValues;

/**
 * What an entry leads to (D-802): its URL, its terms, the entries its
 * relations link, its parent and children, and the media its fields
 * name. `Entry` asks it on its behalf, so `$entry->related('actors')`
 * works wherever an entry is. Every entry shares one, built lazily (the
 * hydrator is a step below the services it reads).
 *
 * Entries come back whatever their status or visibility: what shows
 * them decides, with `isPublished()` and `isRoutable()`.
 */
final readonly class EntryGraph
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private EntryRelations $relations,
		private MediaValues $media
	) {}

	/**
	 * Returns an entry's URL path, or `''` when it has none.
	 */
	public function url(Entry $entry): string
	{
		return $this->urls->entry($entry) ?? '';
	}

	/**
	 * Returns the term entries an entry has of a term type, in the order
	 * front matter lists them, each in the entry's language when it has a
	 * translation in it.
	 *
	 * @return list<Entry>
	 */
	public function terms(Entry $entry, string $taxonomy): array
	{
		if ($this->types->classification($taxonomy) === null) {
			return [];
		}

		$language = $entry->language === '' ? null : $entry->language;
		$terms    = [];

		foreach ($entry->terms[$taxonomy] ?? [] as $slug) {
			$term = $this->content->term($taxonomy, $slug, $language);

			if ($term !== null) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * Returns the entries an entry links to through a relation.
	 *
	 * @return list<Entry>
	 */
	public function related(Entry $entry, string $relation): array
	{
		return $this->relations->related($entry, $relation);
	}

	/**
	 * Returns the entries linking to an entry through a relation.
	 *
	 * @return list<Entry>
	 */
	public function referencedBy(Entry $entry, string $relation): array
	{
		return $this->relations->referencedBy($entry, $relation);
	}

	/**
	 * Returns the people an entry's byline credits, in order.
	 *
	 * @return list<Entry>
	 */
	public function byline(Entry $entry): array
	{
		$byline = $this->types->byline($entry->type->name);

		return $byline === null ? [] : $this->related($entry, $byline->name);
	}

	/**
	 * Returns the people an entry credits, by credit relation: its byline
	 * first, then the type's others by name, leaving out those that
	 * credit no one.
	 *
	 * @return list<array{relation: Relation, byline: bool, people: list<Entry>}>
	 */
	public function credits(Entry $entry): array
	{
		$byline  = $this->types->byline($entry->type->name);
		$credits = $this->types->credits($entry->type->name);

		ksort($credits);

		if ($byline !== null) {
			$credits = [$byline->name => $byline, ...$credits];
		}

		$found = [];

		foreach ($credits as $relation) {
			$people = $this->related($entry, $relation->name);

			if ($people !== []) {
				$found[] = ['relation' => $relation, 'byline' => $relation === $byline, 'people' => $people];
			}
		}

		return $found;
	}

	/**
	 * Returns an entry's parent, or `null`.
	 */
	public function parent(Entry $entry): ?Entry
	{
		return $this->content->parent($entry);
	}

	/**
	 * Returns the entries whose parent is this one.
	 *
	 * @return list<Entry>
	 */
	public function children(Entry $entry): array
	{
		return $this->content->children($entry);
	}

	/**
	 * Returns the media file a value names (a file's id, or an older
	 * path), or `null`.
	 */
	public function media(mixed $value): ?MediaRecord
	{
		try {
			return $this->media->record($value);
		} catch (MediaException) {
			return null;
		}
	}
}
