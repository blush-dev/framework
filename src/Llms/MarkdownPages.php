<?php

/**
 * Markdown pages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use DateTimeInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Parser\BodyFormat;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;

/**
 * The Markdown version of each public page (D-395), for agents and
 * language models: an entry's body as written, under front matter with
 * its title, URL, dates, and summary.
 *
 * Links in a Markdown body and its summary get full URLs
 * (`MarkdownLinks`, D-396); an HTML body is left as written.
 *
 * Every published entry with a URL has one, unlisted entries included,
 * at its URL with `.md` (`/archives/hello` is `/archives/hello.md`,
 * `/about/` is `/about.md`) and the home page at `/index.md`.
 * Directives and components stay as written.
 */
final readonly class MarkdownPages
{
	/**
	 * The media type Markdown pages are served as.
	 */
	public const string MEDIA_TYPE = 'text/markdown';

	public function __construct(
		private ContentRepository $content,
		private ContentUrls $urls,
		private ContentTypes $types,
		private LlmsConfig $config,
		private MarkdownLinks $links
	) {}

	/**
	 * Returns the Markdown version's path for a page's URL path.
	 */
	public static function pathFor(string $url): string
	{
		$url = rtrim($url, '/');

		return ($url === '' ? '/index' : $url) . '.md';
	}

	/**
	 * Returns an entry's Markdown URL path, or `null` when it has none
	 * (Markdown pages are off, or the entry isn't live or has no URL).
	 */
	public function url(Entry $entry): ?string
	{
		if (! $this->config->enabled || $entry->isVirtual() || ! $entry->isPublished()) {
			return null;
		}

		$url = $this->urls->entry($entry);

		// With a home type, its collection is `/`, not the root `index.md`.
		if ($url === null || ($url === '/' && $this->types->home !== null && $entry->type->name !== $this->types->home)) {
			return null;
		}

		return self::pathFor($url);
	}

	/**
	 * Returns every entry with a Markdown version.
	 *
	 * @return list<Entry>
	 */
	public function entries(): array
	{
		if (! $this->config->enabled) {
			return [];
		}

		$entries = $this->content->query()->any()->status(Status::Published)->visibility(Visibility::Public, Visibility::Unlisted)->get();

		return array_values(array_filter([...$entries], fn (Entry $entry): bool => $this->url($entry) !== null));
	}

	/**
	 * Finds the entry whose Markdown version is at a path.
	 */
	public function find(string $path): ?Entry
	{
		return array_find($this->entries(), fn (Entry $entry): bool => $this->url($entry) === $path);
	}

	/**
	 * Returns an entry's Markdown version.
	 */
	public function render(Entry $entry): string
	{
		$url     = $this->urls->entry($entry);
		$summary = $entry->summary();
		$fields  = [
			'title'     => $entry->title,
			'url'       => $url === null ? null : $this->urls->absolute($url),
			'published' => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'   => $entry->updated->format(DateTimeInterface::ATOM),
			'summary'   => $summary === null ? null : $this->links->absolute($summary)
		];

		$lines = ['---'];

		foreach ($fields as $name => $value) {
			if ($value !== null && $value !== '') {
				$lines[] = "{$name}: " . self::scalar($value);
			}
		}

		$lines[] = '---';
		$body    = trim($entry->bodyFormat() === BodyFormat::Markdown ? $this->links->absolute($entry->raw()) : $entry->raw());

		return implode("\n", $lines) . "\n" . ($body === '' ? '' : "\n{$body}\n");
	}

	/**
	 * Returns a string as a double-quoted YAML scalar: JSON's string
	 * syntax is YAML's.
	 */
	private static function scalar(string $value): string
	{
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '""';
	}
}
