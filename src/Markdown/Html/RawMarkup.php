<?php

/**
 * Raw markup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Html;

/**
 * What in a body could carry script (D-495): its raw HTML, as written,
 * and the addresses its links, images, and directives point at. HTML
 * inside code isn't raw HTML, so it isn't here.
 */
final readonly class RawMarkup
{
	/**
	 * @param list<string> $html
	 * @param list<string> $urls
	 */
	public function __construct(
		public array $html = [],
		public array $urls = []
	) {}
}
