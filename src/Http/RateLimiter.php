<?php

/**
 * Rate limiter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Psr\Clock\ClockInterface;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;

/**
 * Counts what someone does within a window, in the cache (the `limits`
 * namespace, never in records, so counting never writes a file of the
 * site's own), and says when they're over a limit (D-758). The MCP
 * server's calls per token are its first use; the API's per-token
 * limits can follow (D-750).
 *
 * A key's window starts with its first hit and lasts `$window` seconds;
 * once it's over, the count starts again.
 */
final readonly class RateLimiter
{
	public function __construct(
		private Caches $caches,
		private ClockInterface $clock
	) {}

	/**
	 * Counts a hit on a key, unless it's over its limit, and returns how
	 * many seconds until it may try again, or `null` when it was counted.
	 *
	 * @throws CacheException
	 */
	public function hit(string $key, int $max, int $window): ?int
	{
		$store  = $this->caches->persistent(CacheNamespace::Limits);
		$now    = $this->clock->now()->getTimestamp();
		$id     = hash('sha256', $key);
		$record = $store->get($id);
		$open   = is_array($record) && is_int($record['since'] ?? null) && is_int($record['count'] ?? null) && $record['since'] + $window > $now;
		$since  = $open ? $record['since'] : $now;
		$count  = $open ? $record['count'] : 0;

		if ($count >= $max) {
			return max(1, $since + $window - $now);
		}

		$store->set($id, ['count' => $count + 1, 'since' => $since], max(1, $since + $window - $now));

		return null;
	}

	/**
	 * Returns how many hits a key has had in its window.
	 *
	 * @throws CacheException
	 */
	public function count(string $key, int $window): int
	{
		$record = $this->caches->persistent(CacheNamespace::Limits)->get(hash('sha256', $key));
		$now    = $this->clock->now()->getTimestamp();

		return is_array($record) && is_int($record['count'] ?? null) && is_int($record['since'] ?? null) && $record['since'] + $window > $now ? $record['count'] : 0;
	}
}
