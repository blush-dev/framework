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

use Blush\Content\Parser\BodyFormat;
use Blush\Markdown\MarkdownException;
use Blush\Markdown\MarkdownParser;

/**
 * An entry's body: its source, and the HTML rendered from it on first
 * use. Entries hold their body as a lazy ghost, so the file is read and
 * parsed only when the body is (listings never touch it).
 */
final class Body
{
	private ?string $html = null;

	/**
	 * @param string $base The entry's folder under `user/content`, for relative media.
	 */
	public function __construct(
		public readonly string $source,
		public readonly BodyFormat $format,
		private readonly MarkdownParser $markdown,
		private readonly string $base = ''
	) {}

	/**
	 * Returns the rendered HTML.
	 *
	 * @throws MarkdownException
	 */
	public function html(): string
	{
		return $this->html ??= $this->format === BodyFormat::Html ? $this->source : $this->markdown->toHtml($this->source, $this->base);
	}

	/**
	 * Renders other Markdown the entry holds, such as its summary.
	 *
	 * @throws MarkdownException
	 */
	public function markdown(string $markdown): string
	{
		return $this->markdown->toHtml($markdown, $this->base);
	}
}
