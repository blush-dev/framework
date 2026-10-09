<?php

/**
 * Menu record.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

/**
 * A site menu as the `menus` table keeps it (D-676): its name, where
 * it's kept (for people), its own label (text or a locale map), and its
 * raw items, which `Menus` resolves for a location. Problems with its
 * shape are kept for `menu:list` and `theme:check`.
 */
final readonly class MenuRecord
{
	/**
	 * @param list<mixed>  $items
	 * @param list<string> $problems
	 */
	public function __construct(
		public string $name,
		public string $location,
		public mixed $label = null,
		public array $items = [],
		public array $problems = []
	) {}
}
