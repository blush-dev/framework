<?php

/**
 * Region item registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Blush\Support\Registry;

/**
 * Maps region item keys to item kinds. An extension adds a kind in a
 * provider's `boot()`:
 *
 *     $this->container->make(RegionItemRegistry::class)->register('ad', AdItem::class);
 *
 * @extends Registry<RegionItem>
 */
final class RegionItemRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = RegionItem::class;
}
