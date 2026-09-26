<?php

/**
 * Cache driver registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Blush\Support\Registry;

/**
 * Maps driver names to store classes. An extension adds a driver in a
 * `resolving()` callback, or in a provider's `boot()`:
 *
 *     $this->container->make(CacheDriverRegistry::class)->register('redis', RedisStore::class);
 *
 * @extends Registry<Store>
 */
final class CacheDriverRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Store::class;
}
