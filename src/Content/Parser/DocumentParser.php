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
 * Parses one kind of content file into a `Document`. Parsers are
 * registered by file extension in the `DocumentParserRegistry` (D-019);
 * extensions can add formats.
 */
interface DocumentParser
{
	/**
	 * Parses a content file's contents.
	 *
	 * @throws InvalidDocument
	 */
	public function parse(string $contents): Document;
}
