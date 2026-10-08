<?php

/**
 * YAML parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Blush\Data\InvalidData;

/**
 * Parses front matter, the only YAML Blush reads (D-631), into plain PHP
 * data: arrays, strings, ints, floats, bools, and nulls. It never builds objects. Timestamps come back as ISO 8601
 * strings; one written without an offset comes back without one, so the
 * caller can read it in the site timezone (D-080).
 *
 * The framework's implementation wraps symfony/yaml for now (D-006); an
 * in-house parser for the YAML subset content uses can replace it later.
 */
interface YamlParser
{
	/**
	 * Parses a YAML string.
	 *
	 * @throws InvalidData
	 */
	public function parse(string $yaml): mixed;
}
