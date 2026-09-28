<?php

/**
 * Menu link registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Blush\Support\Registry;

/**
 * Maps menu item keys to link kinds. An extension adds a kind in a
 * provider's `boot()`:
 *
 *     $this->container->make(MenuLinkRegistry::class)->register('product', ProductLink::class);
 *
 * @extends Registry<MenuLink>
 */
final class MenuLinkRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = MenuLink::class;
}
