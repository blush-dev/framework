<?php

/**
 * llms.txt.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;

/**
 * Builds `/llms.txt` (D-395, from llmstxt.org): a Markdown map of the
 * site for language models. The site's name is its heading and its
 * description (`AppConfig::$description`, D-398) the summary under it,
 * then a section per public type whose `llms` option is on (D-398), by
 * its plural label, linking each public, published entry's Markdown
 * version with its summary (its links given full URLs, D-396). Dated
 * types list newest first, terms and profiles by title (D-401), others
 * by file name. Taxonomies and profiles are off unless a type turns
 * them on (`TypeKind::inLlmsByDefault()`); virtual terms and profiles
 * have no copy, so they're never listed.
 *
 * `/llms-full.txt` (D-402, when `LlmsConfig::$full` is on) has the same
 * heading and summary, then every listed page's Markdown copy in the
 * same order, front matter and all, so a tool gets the whole site in one
 * request.
 */
final readonly class LlmsTxt
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private MarkdownPages $pages,
		private MarkdownLinks $links,
		private AppConfig $app
	) {}

	/**
	 * Returns the file's text.
	 */
	public function render(): string
	{
		$blocks = $this->heading();

		foreach ($this->types() as $type) {
			$items = array_map($this->item(...), $this->entries($type));

			if ($items !== []) {
				$blocks[] = '## ' . self::line($type->labels->plural) . "\n\n" . implode("\n", $items);
			}
		}

		return implode("\n\n", $blocks) . "\n";
	}

	/**
	 * Returns `llms-full.txt`'s text: the heading, then every listed
	 * page's Markdown copy.
	 */
	public function renderFull(): string
	{
		$blocks = $this->heading();

		foreach ($this->types() as $type) {
			foreach ($this->entries($type) as $entry) {
				$blocks[] = rtrim($this->pages->render($entry));
			}
		}

		return implode("\n\n", $blocks) . "\n";
	}

	/**
	 * Returns the site's name as a heading, and its description under it.
	 *
	 * @return list<string>
	 */
	private function heading(): array
	{
		$blocks = ['# ' . self::line($this->app->name)];

		if (trim($this->app->description) !== '') {
			$blocks[] = '> ' . self::line($this->app->description);
		}

		return $blocks;
	}

	/**
	 * Returns the types `llms.txt` may list: public, with their `llms`
	 * option on.
	 *
	 * @return list<ContentType>
	 */
	public function types(): array
	{
		return array_values(array_filter($this->types->all(), static fn (ContentType $type): bool => $type->public && $type->llms));
	}

	/**
	 * Returns how many pages the file lists.
	 */
	public function count(): int
	{
		return array_sum(array_map(fn (ContentType $type): int => count($this->entries($type)), $this->types()));
	}

	/**
	 * Returns a type's entries with Markdown copies, in the type's order
	 * (`ContentType::order()`, D-516).
	 *
	 * @return list<Entry>
	 */
	private function entries(ContentType $type): array
	{
		$query = $this->content->query()->any()->type($type->name)->status(Status::Published)->visibility(Visibility::Public);

		$query = $query->orderBy(...$type->order());

		return array_values(array_filter([...$query->get()], fn (Entry $entry): bool => $this->pages->url($entry) !== null));
	}

	/**
	 * Returns an entry's list item.
	 */
	private function item(Entry $entry): string
	{
		$path = (string) $this->pages->url($entry);

		$title   = self::line($entry->title) ?: $path;
		$link    = '[' . addcslashes($title, '[]\\') . '](' . str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $this->urls->absolute($path)) . ')';
		$summary = self::line($this->links->absolute($entry->summary() ?? ''));

		return '- ' . $link . ($summary === '' ? '' : ": {$summary}");
	}

	/**
	 * Returns text on one line, its whitespace collapsed.
	 */
	private static function line(string $text): string
	{
		return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
	}
}
