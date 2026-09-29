<?php

/**
 * Preview link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Preview;

/**
 * A signed link to an entry's preview: the full URL, and when it stops
 * working (a Unix timestamp).
 */
final readonly class PreviewLink
{
	public function __construct(
		public string $url,
		public int $expires
	) {}
}
