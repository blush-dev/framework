<?php

/**
 * Markdown document parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Override;

/**
 * Parses Markdown files: YAML front matter, then a Markdown body.
 */
final readonly class MarkdownDocumentParser implements DocumentParser
{
	public function __construct(private FrontMatter $frontMatter)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(string $contents): Document
	{
		[$data, $body] = $this->frontMatter->parse($contents);

		return new Document($data, $body, BodyFormat::Markdown);
	}
}
