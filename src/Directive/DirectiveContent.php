<?php

/**
 * Directive content.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

/**
 * What a directive wraps, which decides how it's written in Markdown
 * (D-172).
 */
enum DirectiveContent: string
{
	/**
	 * Nothing: `::name{…}` on a line of its own.
	 */
	case None = 'none';

	/**
	 * A line of text, its label: `::name[text]{…}`, or `:name[text]{…}`
	 * inside a sentence.
	 */
	case Text = 'text';

	/**
	 * Blocks of Markdown: `:::name{…}` … `:::`.
	 */
	case Blocks = 'blocks';
}
