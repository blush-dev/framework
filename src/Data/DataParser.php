<?php

/**
 * Data parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

/**
 * Parses the contents of one data file format into an array. Parsers are
 * registered by file extension in the `DataParserRegistry` (D-019, D-032);
 * extensions can add formats such as NEON.
 */
interface DataParser
{
	/**
	 * Parses a data file's contents. The top level must be a map or a
	 * list; an empty document is an empty array.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidData
	 */
	public function parse(string $contents): array;
}
