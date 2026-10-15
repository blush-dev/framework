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
use Blush\Content\Relation\RelatedEntries;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\Profiles;
use Blush\Content\Visibility;
use Blush\Markdown\MarkdownException;
use Blush\Media\MediaItem;
use Blush\View\Avatar;
use Blush\View\Escaper;
use Blush\View\HtmlAttributes;
use Blush\View\InvalidHtmlAttribute;
use Blush\View\SafeHtml;
use Blush\View\TrustedHtml;
use Blush\Workflow\Status;

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
	 * @param array<string, array<string, string>> $links Target ids by relation name, in order, each with its slug (D-803).
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
		private array $links,
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
	 * Returns its type's relations by name (D-803), or those of one kind,
	 * such as `RelationKind::Credit`: the type's byline relation first
	 * (D-602), then the rest by name.
	 *
	 * @return array<string, Relation>
	 */
	public function relations(?RelationKind $kind = null): array
	{
		return $this->graph?->relations($this, $kind) ?? [];
	}

	/**
	 * Returns the entries it links to through one of its type's relations
	 * (D-585, D-596), such as a movie's `actors`, in the order front
	 * matter lists them, each in its language when translated. A
	 * symmetric relation answers from both ends (related posts include
	 * the ones naming this one), and a translation's links follow the
	 * relation's `translations` rule.
	 */
	public function related(string $relation): RelatedEntries
	{
		return $this->graph?->related($this, $relation) ?? new RelatedEntries();
	}

	/**
	 * Returns its creators (D-804, D-813): the profiles its byline
	 * credits, `related()` for its type's byline relation (D-602), such
	 * as a post's `authors` or a recipe's `cooks`, or none when the type
	 * has no byline.
	 */
	public function creators(): RelatedEntries
	{
		return $this->graph?->creators($this) ?? new RelatedEntries();
	}

	/**
	 * Returns what it's filed under (D-808): the entries every one of its
	 * type's classify relations links, such as a post's categories and
	 * tags, in `relations()` order, each once.
	 */
	public function classifications(): RelatedEntries
	{
		return $this->graph?->ofKind($this, RelationKind::Classify) ?? new RelatedEntries();
	}

	/**
	 * Returns the profiles it credits (D-808): every one of its type's
	 * credit relations, the byline first, merged, each once. `creators()`
	 * is the byline alone.
	 */
	public function credits(): RelatedEntries
	{
		return $this->graph?->ofKind($this, RelationKind::Credit) ?? new RelatedEntries();
	}

	/**
	 * Returns the other entries it refers to (D-808): every one of its
	 * type's reference relations, such as a movie's actors or a post's
	 * related posts, in `relations()` order, each once.
	 */
	public function references(): RelatedEntries
	{
		return $this->graph?->ofKind($this, RelationKind::Reference) ?? new RelatedEntries();
	}

	/**
	 * Returns it in every language it's written in (D-808), by language
	 * code, the default first: its original and its translations, itself
	 * among them.
	 *
	 * @return array<string, Entry>
	 */
	public function translations(): array
	{
		return $this->graph?->translations($this) ?? [$this->language => $this];
	}

	/**
	 * Returns the entries linking to it through a relation, newest
	 * published first: by its name (`actors`), or by its key on one
	 * source type (`movie.actors`). It's the list a relation's reverse
	 * side shows when it has no page of its own.
	 */
	public function referencedBy(string $relation): RelatedEntries
	{
		return $this->graph?->referencedBy($this, $relation) ?? new RelatedEntries();
	}

	/**
	 * Returns whether it links to an entry, by its id, through one of its
	 * type's relations, as written: not through a symmetric relation's
	 * other end or a translation's original.
	 */
	public function linksTo(string $relation, string $id): bool
	{
		return isset($this->links[$relation][$id]);
	}

	/**
	 * Returns the slugs of the entries it links to through a relation, as
	 * written, for core code that builds addresses from them (D-803).
	 *
	 * @internal
	 * @return list<string>
	 */
	public function slugs(string $relation): array
	{
		return array_values($this->links[$relation] ?? []);
	}

	/**
	 * Returns what it links to as written, by relation name: target ids,
	 * in order, each with its slug (D-803).
	 *
	 * @internal
	 * @return array<string, array<string, string>>
	 */
	public function links(): array
	{
		return $this->links;
	}

	/**
	 * Returns a link to it (D-803): `<a href="{url}">{title}</a>`, every
	 * part escaped, with attributes by name. A camelCase name is dashed
	 * (`dataId:` is `data-id`, `ariaCurrent:` is `aria-current`), `true`
	 * prints a bare attribute, and `false` or `null` leaves it out. An
	 * empty title reads "Untitled" in its language; without a URL, it's
	 * the title alone.
	 *
	 * @throws InvalidHtmlAttribute When an attribute isn't named.
	 */
	public function link(string|int|bool|null ...$attributes): SafeHtml
	{
		$title = $this->title === '' ? ($this->graph?->untitled($this) ?? 'Untitled') : $this->title;
		$url   = $this->url();

		if ($url === '') {
			return new TrustedHtml(Escaper::html($title));
		}

		return new TrustedHtml('<a href="' . Escaper::url($url) . '"' . HtmlAttributes::render($attributes, 'link()') . '>' . Escaper::html($title) . '</a>');
	}

	/**
	 * Returns the URL path of its archive under a type (D-596, D-815):
	 * that type's entries linking to it through a relation, by name, or
	 * through the type's byline without one, such as a profile's posts
	 * (`/archives/authors/jane`) or a cook's recipes
	 * (`$cook->archiveUrl('recipe', 'cooks')`). `''` when there's no such
	 * archive; a byline's link can fall back to the profile's own page:
	 *
	 * ```php
	 * <?= url($profile->archiveUrl($entry->type) ?: $profile->url()) ?>
	 * ```
	 */
	public function archiveUrl(ContentType|string $type, ?string $relation = null): string
	{
		return $this->graph?->archiveUrl($this, $type, $relation) ?? '';
	}

	/**
	 * Returns a profile's avatar (D-536, D-809), to print: its portrait
	 * (its `avatar` field), else a square box with up to two initials of
	 * its name, as an inline SVG the theme styles (`Blush\Auth\Avatars`,
	 * which draws accounts' avatars too). Empty for an entry that isn't a profile.
	 * Without a label, it's hidden from assistive technology, for when the
	 * name is printed beside it; with one, it's an image named by it.
	 *
	 * ```php
	 * <?= $profile->avatar(64) ?>
	 * <?= $profile->avatar(label: $profile->title) ?>
	 * ```
	 */
	public function avatar(int $size = 48, string $label = ''): SafeHtml
	{
		if (! $this->type instanceof Profiles) {
			return new TrustedHtml('');
		}

		return new TrustedHtml(($this->graph?->avatar($this, $size) ?? new Avatar($this->title, $size))->html($label));
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
	 * Returns the media file a media field names (D-773, D-814), or
	 * `null`: the same `MediaItem` as `$media->find()` gives, with its
	 * `url`, `width` and `height`, details (`metadata()`, with the alt
	 * text), and, for an image, `img()`.
	 */
	public function media(string $field): ?MediaItem
	{
		return $this->graph?->media($this->field($field));
	}

	/**
	 * Returns whether the entry is live: its status is a live one (D-850)
	 * and its date has come. A draft, an entry scheduled for later, and
	 * one in the trash aren't. This is about time and status only: a published
	 * entry can still be hidden (see `isRoutable()`). Queries find only
	 * published entries unless they ask for others; what an entry leads
	 * to (`related()`, `parent()`, and the rest) isn't
	 * filtered, so check this before showing what they give (D-802).
	 */
	public function isPublished(): bool
	{
		return $this->status->live;
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
