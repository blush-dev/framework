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
use Blush\Content\Parser\BodyFormat;
use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Visibility;
use Blush\Markdown\MarkdownException;

/**
 * One piece of content, as templates and controllers see it. Its front
 * matter is typed by its type's schema (`fields`, keyed by canonical
 * name), and undeclared keys are kept as they were written (`extra`,
 * D-081). The body is a lazy ghost: the file is read and rendered only
 * when `body()` is called.
 *
 * A virtual entry stands in for a term that's referenced but has no file
 * (such as jtcom's authors); it has a title and nothing else.
 */
final readonly class Entry implements Stringable
{
	/**
	 * @param string                      $id          The source path, or `virtual:{type}/{slug}`.
	 * @param string                      $key         The slug with any folders below the type's (see `IndexRecord`).
	 * @param array<string, mixed>        $fields      Typed front matter, by field name.
	 * @param array<string, mixed>        $extra       Undeclared front matter.
	 * @param array<string, list<string>> $terms       Term slugs by taxonomy.
	 * @param bool                        $landing     Whether this is a type folder's landing page.
	 * @param ?SourceFile                 $source      The file, or `null` for a virtual entry.
	 * @param string                      $language    The code of the language it's written in (D-455).
	 */
	public function __construct(
		public string $id,
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
		public ?SourceFile $source,
		private Body $body,
		public string $language = ''
	) {}

	/**
	 * Returns the rendered body.
	 *
	 * @throws MarkdownException
	 */
	public function body(): string
	{
		return $this->body->html();
	}

	/**
	 * Returns the body as written.
	 */
	public function raw(): string
	{
		return $this->body->source();
	}

	/**
	 * Returns the format the body is written in: Markdown, or HTML for
	 * an `.html` entry.
	 */
	public function bodyFormat(): BodyFormat
	{
		return $this->body->format();
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
	 * Returns whether the entry stands in for a term with no file.
	 */
	public function isVirtual(): bool
	{
		return $this->source === null;
	}

	/**
	 * Returns whether the entry is live: published, and its date has
	 * come.
	 */
	public function isPublished(): bool
	{
		return $this->status === Status::Published;
	}

	/**
	 * Returns whether the entry has a URL: it isn't hidden.
	 */
	public function isRoutable(): bool
	{
		return $this->visibility !== Visibility::Hidden;
	}

	/**
	 * Returns whether the entry appears in collections, feeds, and
	 * sitemaps.
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
