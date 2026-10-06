<?php

/**
 * Directive kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

/**
 * The three forms of generic directive (D-026).
 */
enum DirectiveKind: string
{
	/**
	 * `:::name[label]{attrs}` … `:::`, wrapping Markdown blocks.
	 */
	case Container = 'container';

	/**
	 * `::name[label]{attrs}` on a line of its own.
	 */
	case Leaf = 'leaf';

	/**
	 * `:name[text]{attrs}` inside a paragraph.
	 */
	case Inline = 'inline';
}
