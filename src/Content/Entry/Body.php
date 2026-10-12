<?php

/**
 * Entry body.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Blush\Asset\AssetCollector;
use Blush\Markdown\MarkdownException;
use Blush\Markdown\MarkdownParser;

/**
 * An entry's body: its source, and the HTML rendered from it on first
 * use. The source is a lazy ghost, so the file is read and parsed only
 * when it's needed (listings never touch it), and with a `BodyCache`, a
 * cached rendering means it isn't needed at all.
 *
 * It renders in its own asset scope (D-572) and keeps the assets its
 * directives asked for, adding them to the page only when its HTML is
 * asked for (`html()`), not when it's read for an excerpt or a count.
 *
 * What it works out from its HTML (an excerpt, its word count, its
 * summary's rendering) is kept for as long as it lives (D-805), so
 * asking twice works it out once.
 */
final class Body
{
	private ?string $html = null;

	/**
	 * The assets its directives asked for, by handle.
	 *
	 * @var list<string>
	 */
	private array $assets = [];

	/**
	 * What it's worked out, by key.
	 *
	 * @var array<string, string>
	 */
	private array $kept = [];

	/**
	 * @param string $hash     The source file's content hash, which keys the cache.
	 * @param string $language The code of the entry's language when it isn't the default (D-459), which its directives follow, or `''`.
	 */
	public function __construct(
		private readonly BodySource $source,
		private readonly MarkdownParser $markdown,
		private readonly ?BodyCache $cache = null,
		private readonly string $hash = '',
		private readonly string $language = '',
		private readonly ?AssetCollector $collector = null
	) {}

	/**
	 * Returns the body as written.
	 */
	public function source(): string
	{
		return $this->source->text;
	}

	/**
	 * Returns the rendered HTML, and asks for the assets its directives
	 * need, for the page that prints it.
	 *
	 * @throws MarkdownException
	 */
	public function html(): string
	{
		$html = $this->rendered();

		$this->collector?->add(...$this->assets);

		return $html;
	}

	/**
	 * Returns the rendered HTML, rendering it (or reading it from the
	 * cache) on first use, with the assets its directives asked for.
	 *
	 * @throws MarkdownException
	 */
	private function rendered(): string
	{
		if ($this->html !== null) {
			return $this->html;
		}

		$render = fn (): string => $this->cache === null || $this->hash === ''
			? $this->render()
			: $this->cache->remember("body.{$this->key()}", $this->render(...));

		if ($this->collector === null) {
			return $this->html = $render();
		}

		[$this->html, $this->assets] = $this->collector->isolate($render);

		return $this->html;
	}

	/**
	 * Renders other Markdown the entry holds, such as its summary.
	 *
	 * @throws MarkdownException
	 */
	public function markdown(string $markdown): string
	{
		$key    = 'markdown.' . hash('xxh128', $markdown) . ($this->language === '' ? '' : ".{$this->language}");
		$render = fn (): string => $this->markdown->toHtml($markdown, $this->language);

		return $this->keep($key, fn (): string => $this->cache === null ? $render() : $this->cache->remember($key, $render));
	}

	/**
	 * The elements that are never words a reader reads: captions,
	 * navigation (such as a table of contents), players and frames,
	 * controls, footnote marks, and what's hidden or hidden from
	 * assistive technology (such as heading anchors' `#`).
	 */
	private const string UNREAD = 'figcaption, nav, audio, video, iframe, object, script, style, template, noscript, svg, button, '
		. '[role="doc-noteref"], [role="doc-backlink"], [hidden], [aria-hidden="true"]';

	/**
	 * The elements that aren't the post's prose, beyond `UNREAD`
	 * (D-727): anything set apart (figures, asides such as callouts,
	 * details, tables, code), forms and gauges, footnotes, and the
	 * paragraphs and links directives draw, such as a file's link or a
	 * button.
	 */
	private const string NOT_PROSE = 'figure, aside, details, table, pre, form, label, progress, meter, [role="doc-endnotes"], '
		. 'p[class^="directive-"], p[class*=" directive-"], a[class^="directive-button"], a[class*=" directive-button"]';

	/**
	 * The blocks prose is read from (D-727).
	 */
	private const string PROSE = 'p, li, dt, dd';

	/**
	 * Returns the body's first `$words` words of prose in a paragraph, as
	 * 1.x's excerpts did, or `''` for a body without any. Prose is the
	 * text of its paragraphs and list items (D-727), so headings, media,
	 * figures and their captions, callouts, tables, code, footnotes, and
	 * what directives draw that isn't text are left out. When the body is
	 * longer, `$more` is appended inside the paragraph; it's HTML (D-150),
	 * such as a "Continue reading" link, so escape any text in it.
	 *
	 * @throws MarkdownException
	 */
	public function excerpt(int $words = 50, string $more = '…'): string
	{
		$words   = max(1, $words);
		$extract = function () use ($words, $more): string {
			$text = $this->prose();

			if ($text === []) {
				return '';
			}

			$excerpt = implode(' ', array_slice($text, 0, $words));

			return '<p>' . htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . (count($text) > $words ? $more : '') . '</p>';
		};

		$key = "excerpt.{$this->key()}.{$words}." . hash('xxh32', $more);

		return $this->keep($key, fn (): string => $this->cache === null || $this->hash === '' ? $extract() : $this->cache->remember($key, $extract));
	}

	/**
	 * Returns how many words the rendered body has, leaving out what
	 * isn't read (`UNREAD`), such as figure captions, navigation, players,
	 * and heading anchors.
	 *
	 * @throws MarkdownException
	 */
	public function wordCount(): int
	{
		$count = fn (): string => (string) count($this->words());

		$key = "words.{$this->key()}";

		return (int) $this->keep($key, fn (): string => $this->cache === null || $this->hash === '' ? $count() : $this->cache->remember($key, $count));
	}

	/**
	 * Returns the rendered body's words, leaving out what isn't read
	 * (`UNREAD`).
	 *
	 * @return list<string>
	 * @throws MarkdownException
	 */
	private function words(): array
	{
		$document = $this->document(self::UNREAD);

		return self::split($document->body->textContent ?? '');
	}

	/**
	 * Returns the rendered body's prose as words: the text of its
	 * paragraphs and list items, in order, leaving out what isn't read
	 * (`UNREAD`) and what isn't prose (`NOT_PROSE`). A list item's
	 * paragraphs and nested lists are read as their own blocks.
	 *
	 * @return list<string>
	 * @throws MarkdownException
	 */
	private function prose(): array
	{
		$document = $this->document(self::UNREAD . ', ' . self::NOT_PROSE);
		$words    = [];

		foreach ($document->querySelectorAll(self::PROSE) as $block) {
			$block = $block->cloneNode(true);

			if (!$block instanceof Element) {
				continue;
			}

			foreach (iterator_to_array($block->children) as $nested) {
				if (in_array($nested->localName, ['p', 'ul', 'ol', 'dl'], true)) {
					$nested->remove();
				}
			}

			array_push($words, ...self::split($block->textContent ?? ''));
		}

		return $words;
	}

	/**
	 * Returns the rendered body as a document, without the elements
	 * `$remove` selects.
	 *
	 * @throws MarkdownException
	 */
	private function document(string $remove): HTMLDocument
	{
		$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $this->rendered() . '</body>', LIBXML_NOERROR);

		foreach ($document->querySelectorAll($remove) as $element) {
			if ($element->isConnected) {
				$element->remove();
			}
		}

		return $document;
	}

	/**
	 * Splits text into words.
	 *
	 * @return list<string>
	 */
	private static function split(string $text): array
	{
		return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
	}

	/**
	 * Renders the source.
	 *
	 * @throws MarkdownException
	 */
	private function render(): string
	{
		return $this->markdown->toHtml($this->source->text, $this->language);
	}

	/**
	 * Returns what's kept under a key, or works it out and keeps it.
	 *
	 * @param Closure(): string $compute
	 */
	private function keep(string $key, Closure $compute): string
	{
		return $this->kept[$key] ??= $compute();
	}

	/**
	 * Returns what keys the body's cached renderings: its hash, and its
	 * language when it isn't the default, since its directives follow it.
	 */
	private function key(): string
	{
		return $this->language === '' ? $this->hash : "{$this->hash}.{$this->language}";
	}
}
