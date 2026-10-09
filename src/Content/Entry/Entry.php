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
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Visibility;
use Blush\Markdown\MarkdownException;

/**
 * One piece of content, as templates and controllers see it. Its front
 * matter is typed by its type's schema (`fields`, keyed by canonical
 * name), and undeclared keys are kept as they were written (`extra`,
 * D-081). The body is a lazy ghost: its Markdown is read and rendered
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
	 * @param array<string, mixed>        $extra       Undeclared front matter.
	 * @param array<string, list<string>> $terms       Term slugs by taxonomy.
	 * @param bool                        $landing     Whether this is a type folder's landing page.
	 * @param string                      $language    The code of the language it's written in (D-455).
	 * @param ?string                     $id          Its id (D-477); `null` only for a file without one, which the filesystem driver's tools build.
	 * @param ?string                     $version     Its version (D-648), for the edit-conflict check: on files, a hash of its file.
	 * @param ?string                     $parentId    Its parent's id, in a type that nests.
	 * @param ?string                     $originalId  The id of the original it's a translation of.
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
		public array $extra,
		public array $terms,
		public bool $landing,
		private Body $body,
		public string $language = '',
		public ?string $id = null,
		public ?string $version = null,
		public ?string $parentId = null,
		public ?string $originalId = null
	) {}

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
	 * Returns a front matter value by field name, or an undeclared key's
	 * raw value.
	 */
	public function field(string $name, mixed $default = null): mixed
	{
		return $this->fields[$name] ?? $this->extra[$name] ?? $default;
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
	 * Returns the view names front matter asks for (`template`, or 1.x's
	 * `view`), without a `.php` extension.
	 *
	 * @return list<string>
	 */
	public function templates(): array
	{
		$templates = $this->fields['template'] ?? [];
		$views     = [];

		foreach (is_array($templates) ? $templates : [] as $view) {
			if (is_string($view)) {
				$views[] = str_ends_with($view, '.php') ? substr($view, 0, -4) : $view;
			}
		}

		return $views;
	}

	/**
	 * Returns the slugs of the terms the entry has in a taxonomy.
	 *
	 * @return list<string>
	 */
	public function terms(string $taxonomy): array
	{
		return $this->terms[$taxonomy] ?? [];
	}

	/**
	 * Returns whether the entry has a term.
	 */
	public function hasTerm(string $taxonomy, string $slug): bool
	{
		return in_array($slug, $this->terms($taxonomy), true);
	}

	/**
	 * Returns whether the entry is live: its status is published and its
	 * date has come. A draft, an entry scheduled for later, and one in
	 * the trash aren't. This is about time and status only: a published
	 * entry can still be hidden (see `isRoutable()`). Queries find only
	 * published entries unless they ask for others; `parent()` and
	 * `children()` don't filter, so check this before showing what they
	 * give.
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
