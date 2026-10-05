<?php

/**
 * Entry body source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

/**
 * A body as written, in Markdown. Entries get it as a lazy ghost that
 * reads and parses the file on first use.
 */
final readonly class BodySource
{
	public function __construct(
		public string $text = ''
	) {}
}
