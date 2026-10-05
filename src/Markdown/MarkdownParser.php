<?php

/**
 * Markdown parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * Converts Markdown to HTML. Front matter is split off before the body gets
 * here. The framework's implementation wraps league/commonmark for now
 * (D-006, D-080); an in-house parser is the long-term goal.
 */
interface MarkdownParser
{
	/**
	 * Converts a Markdown string to HTML. `$language` is the code of the
	 * language it's written in when that isn't the site's default
	 * (D-459), which its directives are given, so components in a
	 * translation find that language's entries.
	 *
	 * @throws MarkdownException
	 */
	public function toHtml(string $markdown, string $language = ''): string;
}
