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
use Blush\Content\Parser\BodyFormat;
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
	 * @param string $base The entry's folder under `user/content`, for relative media.
	 * @param string $hash The source file's content hash, which keys the cache.
	 */
	public function __construct(
		private readonly BodySource $source,
		private readonly MarkdownParser $markdown,
		private readonly string $base = '',
		private readonly ?BodyCache $cache = null,
		private readonly string $hash = ''
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
			: $this->cache->remember("body.{$this->hash}", $this->render(...));
	}

	/**
	 * Renders other Markdown the entry holds, such as its summary.
	 *
	 * @throws MarkdownException
	 */
	public function markdown(string $markdown): string
	{
		$render = fn (): string => $this->markdown->toHtml($markdown, $this->base);

		return $this->cache === null
			? $render()
			: $this->cache->remember('markdown.' . hash('xxh128', "{$this->base}\0{$markdown}"), $render);
	}

	/**
	 * Returns the body's first `$words` words (leaving out figure
	 * captions) in a paragraph, as 1.x's excerpts did, or `''` for an
	 * empty body.
	 *
	 * @throws MarkdownException
	 */
	public function excerpt(int $words = 50, string $more = '…'): string
	{
		$words   = max(1, $words);
		$extract = function () use ($words, $more): string {
			$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $this->html() . '</body>', LIBXML_NOERROR);

			foreach ($document->querySelectorAll('figcaption') as $caption) {
				$caption->remove();
			}

			$text = preg_split('/\s+/u', trim($document->body->textContent ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

			if ($text === []) {
				return '';
			}

			$excerpt = implode(' ', array_slice($text, 0, $words));

			return '<p>' . htmlspecialchars($excerpt . (count($text) > $words ? $more : ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
		};

		return $this->cache === null || $this->hash === ''
			? $extract()
			: $this->cache->remember("excerpt.{$this->hash}.{$words}." . hash('xxh32', $more), $extract);
	}

	/**
	 * Renders the source.
	 *
	 * @throws MarkdownException
	 */
	private function render(): string
	{
		return $this->source->format === BodyFormat::Html ? $this->source->text : $this->markdown->toHtml($this->source->text, $this->base);
	}
}
