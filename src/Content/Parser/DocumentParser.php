<?php

/**
 * Document parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

/**
 * Parses a content document: YAML front matter, then a Markdown body
 * (D-501). Every storage driver hands documents over in this shape; the
 * filesystem driver keeps each as a `.md` file.
 */
final readonly class DocumentParser
{
	public function __construct(private FrontMatter $frontMatter)
	{}

	/**
	 * Parses a document's contents.
	 *
	 * @throws InvalidDocument
	 */
	public function parse(string $contents): Document
	{
		[$data, $body] = $this->frontMatter->parse($contents);

		return new Document($data, $body);
	}
}
