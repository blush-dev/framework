<?php

/**
 * Menu link target.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

/**
 * Where a menu item's link leads: its URL, and the label it brings (an
 * entry's title), or `''` when the item must name itself.
 */
final readonly class LinkTarget
{
	public function __construct(
		public string $url,
		public string $label = ''
	) {}
}
