<?php

/**
 * Markdown rendering context.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * What the Markdown being converted belongs to, for the parts of
 * rendering that need it: `base` is the entry's folder under
 * `user/content`, which relative media paths resolve against. The parser
 * sets it for each conversion.
 */
final class MarkdownContext
{
	public string $base = '';
}
