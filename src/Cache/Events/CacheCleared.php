<?php

/**
 * Cache cleared event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache\Events;

/**
 * Dispatched after `Caches::clear()` empties the cache store, whether from
 * `cache:clear`, the admin's Clear caches, a publish, or `cache:compile`,
 * with the namespaces it cleared. Extensions listen for it to purge a CDN
 * or an edge cache, or to warm pages again.
 */
final readonly class CacheCleared
{
	/**
	 * @param list<string> $namespaces
	 */
	public function __construct(public array $namespaces)
	{}
}
