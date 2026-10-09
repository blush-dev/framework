<?php

/**
 * Links an entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Blush\Content\Entry\Entry;

/**
 * A menu link kind that leads to an entry (D-676), such as `entry` and
 * `term`: an item names it in a readable form (`page/about`) and by its
 * id in `ref`, which wins when it finds the entry, so renaming the
 * entry can't break the link. `menu:refs` files both forms.
 */
interface LinksEntry
{
	/**
	 * The item key holding the entry's id.
	 */
	public const string REF = 'ref';

	/**
	 * Returns the entry an item leads to, whatever its status: by its
	 * `ref` when that finds one, else by its readable value, else `null`.
	 *
	 * @param array<string, mixed> $item The whole item.
	 */
	public function entry(string $value, array $item): ?Entry;

	/**
	 * Returns the readable value that names an entry, such as `page/about`.
	 */
	public function value(Entry $entry): string;
}
