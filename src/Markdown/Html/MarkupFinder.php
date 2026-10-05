<?php

/**
 * Markup finder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Html;

use Blush\Markdown\MarkdownException;

/**
 * Reads a Markdown body for its raw HTML and addresses (D-495), the way
 * the parser reads it, so HTML in code doesn't count.
 */
interface MarkupFinder
{
	/**
	 * @throws MarkdownException
	 */
	public function find(string $markdown): RawMarkup;
}
