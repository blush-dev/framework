<?php

/**
 * Menu link base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

/**
 * A kind of menu item link (D-199), keyed in the item by its registered
 * name: `entry: page/about`, `route: feed`, `url: https://…`. An item has
 * at most one link.
 *
 * Kinds are registered in `MenuLinkRegistry` and built through the
 * container, so a kind's constructor can ask for services:
 *
 *     $this->container->make(MenuLinkRegistry::class)->register('product', ProductLink::class);
 */
abstract class MenuLink
{
	/**
	 * Returns the other item keys this kind reads, such as a route's
	 * `params`, so they aren't taken for theme-declared fields.
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return [];
	}

	/**
	 * Returns what's wrong with an item's link value, or `null` when it
	 * has the right shape. Checked when a menu loads; whether it leads
	 * anywhere is `resolve()`'s job.
	 *
	 * @param array<string, mixed> $item The whole item.
	 */
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && trim($value) !== '' ? null : 'must be a non-empty string.';
	}

	/**
	 * Returns where a link leads, in a locale.
	 *
	 * @param  array<string, mixed> $item The whole item.
	 * @throws UnresolvedLink When it doesn't lead anywhere now.
	 */
	abstract public function resolve(string $value, array $item, string $locale): LinkTarget;
}
