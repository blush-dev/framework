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

use Blush\Auth\Avatars;
use Blush\Content\Entries;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Relation\RelatedEntries;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\Relations;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaException;
use Blush\Media\MediaValues;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;
use Blush\Translation\Translator;
use Blush\View\Avatar;

/**
 * What an entry leads to (D-802): its URL, its type's relations and the
 * entries they link (D-803), its parent and children, the media its
 * fields name, a profile's picture (D-809), and the word for an entry
 * without a title. `Entry` asks
 * it on its behalf, so `$entry->related('actors')` works wherever an
 * entry is. Every entry shares one, built lazily (the hydrator is a step
 * below the services it reads).
 *
 * Every answer is kept in the `RequestMemo` (D-805), by the entry's id,
 * version, and language, so asking twice is one lookup.
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
		private Relations $definitions,
		private MediaValues $media,
		private Translator $translator,
		private Messages $messages,
		private RequestMemo $memo,
		private Avatars $avatars
	) {}

	/**
	 * Returns an entry's URL path, or `''` when it has none.
	 */
	public function url(Entry $entry): string
	{
		return $this->urls->entry($entry) ?? '';
	}

	/**
	 * Returns an entry's type's relations by name, or those of one kind:
	 * the byline first, then the rest by name.
	 *
	 * @return array<string, Relation>
	 */
	public function relations(Entry $entry, ?RelationKind $kind = null): array
	{
		return $this->memo->remember('relations|' . $entry->type->name . '|' . ($kind->value ?? ''), fn (): array => $this->relationsOf($entry, $kind));
	}

	/**
	 * Works out an entry's type's relations, for `relations()`.
	 *
	 * @return array<string, Relation>
	 */
	private function relationsOf(Entry $entry, ?RelationKind $kind): array
	{
		$relations = $this->definitions->for($entry->type->name);

		if ($kind !== null) {
			$relations = array_filter($relations, static fn (Relation $relation): bool => $relation->kind === $kind);
		}

		ksort($relations);

		$byline = $this->types->byline($entry->type->name);

		return $byline !== null && isset($relations[$byline->name])
			? [$byline->name => $relations[$byline->name], ...$relations]
			: $relations;
	}

	/**
	 * Returns the entries an entry links to through a relation.
	 */
	public function related(Entry $entry, string $relation): RelatedEntries
	{
		return $this->memo->remember(self::key("related|{$relation}", $entry), fn (): RelatedEntries => new RelatedEntries(
			$this->relations->related($entry, $relation),
			$this->definitions->find($entry->type->name, $relation),
			$this->types->byline($entry->type->name)?->name === $relation
		));
	}

	/**
	 * Returns the profiles an entry's byline credits, or none when its
	 * type has no byline.
	 */
	public function byline(Entry $entry): RelatedEntries
	{
		$byline = $this->types->byline($entry->type->name);

		return $byline === null ? new RelatedEntries() : $this->related($entry, $byline->name);
	}

	/**
	 * Returns the entries an entry links to through every relation of a
	 * kind on its type, in `relations()` order, each once.
	 */
	public function ofKind(Entry $entry, RelationKind $kind): RelatedEntries
	{
		return $this->memo->remember(self::key("kind|{$kind->value}", $entry), function () use ($entry, $kind): RelatedEntries {
			$found = [];

			foreach (array_keys($this->relations($entry, $kind)) as $relation) {
				foreach ($this->related($entry, $relation) as $linked) {
					$found[$linked->id ?? $linked->path] ??= $linked;
				}
			}

			return new RelatedEntries(array_values($found));
		});
	}

	/**
	 * Returns an entry in every language it's written in, by language
	 * code.
	 *
	 * @return array<string, Entry>
	 */
	public function translations(Entry $entry): array
	{
		return $this->memo->remember(self::key('translations', $entry), fn (): array => $this->content->translations($entry));
	}

	/**
	 * Returns the entries linking to an entry through a relation: a key on
	 * a source type (`movie.actors`), or a name on any type.
	 */
	public function referencedBy(Entry $entry, string $relation): RelatedEntries
	{
		return $this->memo->remember(self::key("referencedBy|{$relation}", $entry), fn (): RelatedEntries => new RelatedEntries(
			$this->relations->referencedBy($entry, $relation),
			str_contains($relation, '.') ? $this->definitions->byKey($relation) : array_find($this->definitions->relations, static fn (Relation $definition): bool => $definition->name === $relation)
		));
	}

	/**
	 * Returns the word for an entry without a title, in its language.
	 */
	public function untitled(Entry $entry): string
	{
		return $this->memo->remember("untitled|{$entry->locale}", fn (): string => $this->translator->text($this->messages->t('untitled', 'Untitled'), $entry->locale));
	}

	/**
	 * Returns an entry's parent, or `null`.
	 */
	public function parent(Entry $entry): ?Entry
	{
		return $this->memo->remember(self::key('parent', $entry), fn (): ?Entry => $this->content->parent($entry));
	}

	/**
	 * Returns the entries whose parent is this one.
	 *
	 * @return list<Entry>
	 */
	public function children(Entry $entry): array
	{
		return $this->memo->remember(self::key('children', $entry), fn (): array => $this->content->children($entry));
	}

	/**
	 * Returns the media file a value names (a file's id, or an older
	 * path), or `null`.
	 */
	public function media(mixed $value): ?MediaRecord
	{
		$find = function () use ($value): ?MediaRecord {
			try {
				return $this->media->record($value);
			} catch (MediaException) {
				return null;
			}
		};

		return is_string($value) ? $this->memo->remember("media|{$value}", $find) : $find();
	}

	/**
	 * Returns a profile's avatar (D-809), from `Avatars`.
	 */
	public function avatar(Entry $profile, int $size): Avatar
	{
		return $this->avatars->profile($profile, $size);
	}

	/**
	 * Returns the memo's key for an answer about an entry: by its id (or
	 * where it's kept, for one without), version, and language.
	 */
	private static function key(string $answer, Entry $entry): string
	{
		return implode('|', [$answer, $entry->id ?? "path:{$entry->path}", $entry->version ?? '', $entry->language]);
	}
}
