<?php

/**
 * Content cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Closure;

/**
 * Keeps values derived from content and site data (rendered bodies,
 * compiled tokens, fragments) under the content version, so they're
 * computed once per version and never read after it changes. With
 * caching off, the value is computed every time.
 *
 *     $html = $cache->remember(CacheNamespace::Fragments, 'menu.primary', fn (): string => $this->menu());
 */
final readonly class ContentCache
{
	public function __construct(
		private Caches $caches,
		private ContentVersion $version
	) {}

	/**
	 * Returns the value for a key in a namespace, or computes and keeps
	 * it. A `null` result isn't kept.
	 *
	 * @template T
	 * @param  Closure(): T $compute
	 * @return T
	 * @throws CacheException When the namespace's store can't be built.
	 */
	public function remember(string|CacheNamespace $namespace, string $key, Closure $compute): mixed
	{
		if (! $this->caches->enabled()) {
			return $compute();
		}

		return $this->caches->store($namespace)->remember(hash('xxh128', $this->version->current() . ' ' . $key), null, $compute);
	}
}
