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

use Dom\HTMLDocument;
use Blush\Markdown\MarkdownException;
use Blush\Markdown\MarkdownParser;

/**
 * An entry's body: its source, and the HTML rendered from it on first
 * use. The source is a lazy ghost, so the file is read and parsed only
 * when it's needed (listings never touch it), and with a `BodyCache`, a
 * cached rendering means it isn't needed at all.
 */
final class Body
{
	private ?string $html = null;

	/**
	 * @param string $hash     The source file's content hash, which keys the cache.
	 * @param string $language The code of the entry's language when it isn't the default (D-459), which its directives follow, or `''`.
	 */
	public function __construct(
		private readonly BodySource $source,
		private readonly MarkdownParser $markdown,
		private readonly ?BodyCache $cache = null,
		private readonly string $hash = '',
		private readonly string $language = ''
	) {}

	/**
	 * Returns the body as written.
	 */
	public function source(): string
	{
		return $this->source->text;
	}

	/**
	 * Returns the rendered HTML.
	 *
	 * @throws MarkdownException
	 */
	public function html(): string
	{
		return $this->html ??= $this->cache === null || $this->hash === ''
			? $this->render()
			: $this->cache->remember("body.{$this->key()}", $this->render(...));
	}

	/**
	 * Renders other Markdown the entry holds, such as its summary.
	 *
	 * @throws MarkdownException
	 */
	public function markdown(string $markdown): string
	{
		$render = fn (): string => $this->markdown->toHtml($markdown, $this->language);

		return $this->cache === null
			? $render()
			: $this->cache->remember('markdown.' . hash('xxh128', $markdown) . ($this->language === '' ? '' : ".{$this->language}"), $render);
	}

	/**
	 * Returns the body's first `$words` words (leaving out figure
	 * captions) in a paragraph, as 1.x's excerpts did, or `''` for an
	 * empty body. When the body is longer, `$more` is appended inside the
	 * paragraph; it's HTML (D-150), such as a "Continue reading" link, so
	 * escape any text in it.
	 *
	 * @throws MarkdownException
	 */
	public function excerpt(int $words = 50, string $more = '…'): string
	{
		$words   = max(1, $words);
		$extract = function () use ($words, $more): string {
			$text = $this->words();

			if ($text === []) {
				return '';
			}

			$excerpt = implode(' ', array_slice($text, 0, $words));

			return '<p>' . htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . (count($text) > $words ? $more : '') . '</p>';
		};

		return $this->cache === null || $this->hash === ''
			? $extract()
			: $this->cache->remember("excerpt.{$this->key()}.{$words}." . hash('xxh32', $more), $extract);
	}

	/**
	 * Returns how many words the rendered body has, leaving out figure
	 * captions, navigation (such as a table of contents), and heading
	 * anchors.
	 *
	 * @throws MarkdownException
	 */
	public function wordCount(): int
	{
		$count = fn (): string => (string) count($this->words());

		return (int) ($this->cache === null || $this->hash === '' ? $count() : $this->cache->remember("words.{$this->key()}", $count));
	}

	/**
	 * Returns the rendered body's words, leaving out figure captions,
	 * navigation (such as a table of contents), and what's hidden from
	 * assistive technology (such as heading anchors' `#`), which aren't
	 * prose.
	 *
	 * @return list<string>
	 * @throws MarkdownException
	 */
	private function words(): array
	{
		$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $this->html() . '</body>', LIBXML_NOERROR);

		foreach ($document->querySelectorAll('figcaption, nav, [aria-hidden="true"]') as $element) {
			$element->remove();
		}

		return preg_split('/\s+/u', trim($document->body->textContent ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
	 * Returns what keys the body's cached renderings: its hash, and its
	 * language when it isn't the default, since its directives follow it.
	 */
	private function key(): string
	{
		return $this->language === '' ? $this->hash : "{$this->hash}.{$this->language}";
	}
}
