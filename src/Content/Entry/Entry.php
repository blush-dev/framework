<?php

/**
 * Content entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use DateTimeImmutable;
use Stringable;
use Override;
use Blush\Content\Relation\Relation;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Visibility;
use Blush\Markdown\MarkdownException;
use Blush\Media\Index\MediaRecord;

/**
 * One piece of content, as templates and controllers see it. Its front
 * matter is typed by its type's schema (`fields`, keyed by canonical
 * name). Only declared fields are read (D-786): a key nothing declares
 * stays in its file but isn't here. The body is a lazy ghost: its Markdown is read and rendered
 * only when `content()` is called.
 *
 * Entries are built from their records (D-649), whatever keeps them.
 * Every entry is stored (D-584): a term or profile that's named but
 * isn't kept isn't an entry, and neither is a file without an id
 * (D-656).
 */
final readonly class Entry implements Stringable
{
	/**
	 * @param string                      $path        Where its store keeps it, for showing (a file's path from the content folder), or `''`.
	 * @param string                      $key         Its slug after its parents' (D-656); `''` for a landing page.
	 * @param array<string, mixed>        $fields      Typed front matter, by field name.
	 * @param array<string, list<string>> $terms       Term slugs by taxonomy.
	 * @param bool                        $landing     Whether this is a type folder's landing page.
	 * @param string                      $language    The code of the language it's written in (D-455).
	 * @param ?string                     $id          Its id (D-477); `null` only for a file without one, which the filesystem driver's tools build.
	 * @param ?string                     $version     Its version (D-648), for the edit-conflict check: on files, a hash of its file.
	 * @param ?string                     $parentId    Its parent's id, in a type that nests.
	 * @param ?string                     $originalId  The id of the original it's a translation of.
	 * @param ?EntryGraph                 $graph       What it leads to (D-802); without it, it leads nowhere.
	 */
	public function __construct(
		public string $path,
		public ContentType $type,
		public string $slug,
		public string $key,
		public string $title,
		public Status $status,
		public Visibility $visibility,
		public ?DateTimeImmutable $published,
		public DateTimeImmutable $updated,
		public string $locale,
		public array $fields,
		public array $terms,
		public bool $landing,
		private Body $body,
		public string $language = '',
		public ?string $id = null,
		public ?string $version = null,
		public ?string $parentId = null,
		public ?string $originalId = null,
		private ?EntryGraph $graph = null
	) {}

	/**
	 * Returns its URL path, or `''` when it has none.
	 */
	public function url(): string
	{
		return $this->graph?->url($this) ?? '';
	}

	/**
	 * Returns the rendered content (D-649).
	 *
	 * @throws MarkdownException
	 */
	public function content(): string
	{
		return $this->body->html();
	}

	/**
	 * Returns the content as written: its Markdown.
	 */
	public function raw(): string
	{
		return $this->body->source();
	}

	/**
	 * Returns a declared field's value by its name.
	 */
	public function field(string $name, mixed $default = null): mixed
	{
		return $this->fields[$name] ?? $default;
	}

	/**
	 * Returns whether the entry has a front matter value.
	 */
	public function has(string $name): bool
	{
		return $this->field($name) !== null;
	}

	/**
	 * Returns the subtitle, or `''`.
	 */
	public function subtitle(): string
	{
		$subtitle = $this->fields['subtitle'] ?? '';

		return is_string($subtitle) ? $subtitle : '';
	}

	/**
	 * Returns the summary as written (Markdown, 1.x's `excerpt`), or
	 * `null`.
	 */
	public function summary(): ?string
	{
		$summary = $this->fields['summary'] ?? null;

		return is_string($summary) ? $summary : null;
	}

	/**
	 * Returns the excerpt as HTML: the rendered summary, or else the
	 * body's first `$words` words (leaving out figure captions) in a
	 * paragraph, as 1.x did, ending with `$more` (HTML) when the body is
	 * longer.
	 *
	 * @throws MarkdownException
	 */
	public function excerpt(int $words = 50, string $more = '…'): string
	{
		$summary = $this->summary();

		if ($summary !== null) {
			return $this->body->markdown($summary);
		}

		return $this->body->excerpt($words, $more);
	}

	/**
	 * Returns how many words the body has, leaving out figure captions.
	 *
	 * @throws MarkdownException
	 */
	public function wordCount(): int
	{
		return $this->body->wordCount();
	}

	/**
	 * Returns the body's reading time in whole minutes, at least 1.
	 *
	 * @throws MarkdownException
	 */
	public function readingTime(int $wordsPerMinute = 200): int
	{
		return max(1, (int) ceil($this->wordCount() / max(1, $wordsPerMinute)));
	}

	/**
	 * Returns the view name front matter asks for (`template`, or 1.x's
	 * `view`), without a `.php` extension, as a list: none or one.
	 *
	 * @return list<string>
	 */
	public function templates(): array
	{
		$view = $this->fields['template'] ?? null;

		if (! is_string($view) || $view === '') {
			return [];
		}

		return [str_ends_with($view, '.php') ? substr($view, 0, -4) : $view];
	}

	/**
	 * Returns its term entries of a term type (one a classify relation
	 * files entries under, D-593), such as its tags, in the order front
	 * matter lists them, each in its language when translated. Their
	 * slugs are in `$terms`.
	 *
	 * @return list<Entry>
	 */
	public function terms(string $taxonomy): array
	{
		return $this->graph?->terms($this, $taxonomy) ?? [];
	}

	/**
	 * Returns whether the entry has a term, by its slug.
	 */
	public function hasTerm(string $taxonomy, string $slug): bool
	{
		return in_array($slug, $this->terms[$taxonomy] ?? [], true);
	}

	/**
	 * Returns the entries it links to through one of its type's relations
	 * (D-585, D-596), such as a movie's `actors`, in the order front
	 * matter lists them, each in its language when translated. A
	 * symmetric relation answers from both ends (related posts include
	 * the ones naming this one), and a translation's links follow the
	 * relation's `translations` rule.
	 *
	 * @return list<Entry>
	 */
	public function related(string $relation): array
	{
		return $this->graph?->related($this, $relation) ?? [];
	}

	/**
	 * Returns the entries linking to it through a relation, newest
	 * published first: by its name (`actors`), or by its key on one
	 * source type (`movie.actors`). It's the list a relation's reverse
	 * side shows when it has no page of its own.
	 *
	 * @return list<Entry>
	 */
	public function referencedBy(string $relation): array
	{
		return $this->graph?->referencedBy($this, $relation) ?? [];
	}

	/**
	 * Returns the people its byline credits (D-602): its type's byline
	 * relation, such as a post's `authors`, in order.
	 *
	 * @return list<Entry>
	 */
	public function byline(): array
	{
		return $this->graph?->byline($this) ?? [];
	}

	/**
	 * Returns the first person its byline credits, such as a post's
	 * author, or `null` when it credits no one.
	 */
	public function author(): ?Entry
	{
		return array_first($this->byline());
	}

	/**
	 * Returns the people it credits, by credit relation (D-602): its
	 * byline first, then its type's others by name, each with the people
	 * it credits, leaving out those that credit no one. A relation's
	 * `label` and `singular` name it ("Photographers", "Photographer").
	 *
	 * @return list<array{relation: Relation, byline: bool, people: list<Entry>}>
	 */
	public function credits(): array
	{
		return $this->graph?->credits($this) ?? [];
	}

	/**
	 * Returns its parent: a page's or a nesting collection's entry's, or
	 * `null` when it has none.
	 */
	public function parent(): ?Entry
	{
		return $this->graph?->parent($this);
	}

	/**
	 * Returns its parents from the top down, such as for breadcrumbs.
	 *
	 * @return list<Entry>
	 */
	public function ancestors(): array
	{
		$ancestors = [];
		$seen      = [$this->id ?? $this->path => true];
		$entry     = $this;

		while (($entry = $entry->parent()) !== null && ! isset($seen[$entry->id ?? $entry->path])) {
			$seen[$entry->id ?? $entry->path] = true;
			array_unshift($ancestors, $entry);
		}

		return $ancestors;
	}

	/**
	 * Returns the entries whose parent is this one, by `position` and
	 * then title: a page's subpages, or a term's child terms.
	 *
	 * @return list<Entry>
	 */
	public function children(): array
	{
		return $this->graph?->children($this) ?? [];
	}

	/**
	 * Returns the media file a media field names (D-773), or `null`: its
	 * `url`, its `width` and `height`, and its details (`metadata()`, with
	 * the alt text).
	 */
	public function media(string $field): ?MediaRecord
	{
		return $this->graph?->media($this->field($field));
	}

	/**
	 * Returns whether the entry is live: its status is published and its
	 * date has come. A draft, an entry scheduled for later, and one in
	 * the trash aren't. This is about time and status only: a published
	 * entry can still be hidden (see `isRoutable()`). Queries find only
	 * published entries unless they ask for others; what an entry leads
	 * to (`terms()`, `related()`, `parent()`, and the rest) isn't
	 * filtered, so check this before showing what they give (D-802).
	 */
	public function isPublished(): bool
	{
		return $this->status === Status::Published;
	}

	/**
	 * Returns whether the entry has a page of its own, by visibility
	 * alone: public and unlisted entries do, hidden ones (1.x's
	 * `hidden`, or a `_` file name) don't. It doesn't look at status,
	 * so a draft is routable, though its page isn't served until it's
	 * published. Check it with `isPublished()` before linking to an
	 * entry: `$entry->isPublished() && $entry->isRoutable()`.
	 */
	public function isRoutable(): bool
	{
		return $this->visibility !== Visibility::Hidden;
	}

	/**
	 * Returns whether the entry appears in collections, feeds, and
	 * sitemaps: published, public (not unlisted or hidden), and not a
	 * listing's landing page. Everything listed is routable; an unlisted
	 * entry is routable but not listed.
	 */
	public function isListed(): bool
	{
		return $this->visibility === Visibility::Public && $this->isPublished() && ! $this->landing;
	}

	/**
	 * Returns the slug, as 1.x entries did.
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->slug;
	}
}
