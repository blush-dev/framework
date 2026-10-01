<?php

/**
 * Bracketed span node.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Text in brackets followed by attributes, `[text]{.class #id}`, as
 * Pandoc writes a span (D-305). It holds the bracketed inlines; the
 * attributes extension gives it its attributes.
 */
final class BracketedSpan extends AbstractInline
{
}
