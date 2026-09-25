<?php

/**
 * Document parser registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Blush\Support\Registry;

/**
 * Maps content file extensions (lowercase, without the dot) to
 * `DocumentParser` classes. Files with other extensions aren't content. It
 * starts with the built-in formats; an extension adds or replaces one by
 * registering in a `resolving()` callback.
 *
 * @extends Registry<DocumentParser>
 */
final class DocumentParserRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = DocumentParser::class;
}
