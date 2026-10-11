<?php

/**
 * Token throttle.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Psr\Clock\ClockInterface;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;

/**
 * Counts requests with a token that isn't one (wrong, revoked, expired,
 * or its account's suspended) by address, in the persistent `logins`
 * store (D-750). Past `AuthConfig::$maxAttempts` times four, as for
 * sign-ins from one address, the address's tokens are refused until
 * `lockout` seconds after the first failure counted. Guessing 32 random
 * bytes isn't a real risk; this keeps a script with a dead token from
 * hammering the site. A good token doesn't clear the count, so a stolen
 * token can't be used to reset it.
 */
final readonly class TokenThrottle
{
	public function __construct(
		private Caches $caches,
		private AuthConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Whether tokens from an address are refused for now.
	 *
	 * @throws CacheException
	 */
	public function isLockedOut(string $ip): bool
	{
		return $this->count($ip) >= $this->config->maxAttempts * 4;
	}

	/**
	 * Records a token that wasn't one.
	 *
	 * @throws CacheException
	 */
	public function fail(string $ip): void
	{
		$store  = $this->caches->persistent(CacheNamespace::Logins);
		$now    = $this->clock->now()->getTimestamp();
		$record = $store->get(self::key($ip));
		$since  = is_array($record) && is_int($record['since'] ?? null) ? $record['since'] : $now;

		$store->set(self::key($ip), ['count' => $this->count($ip) + 1, 'since' => $since], max(1, $since + $this->config->lockout - $now));
	}

	/**
	 * Returns an address's failures within its window.
	 *
	 * @throws CacheException
	 */
	private function count(string $ip): int
	{
		$record = $this->caches->persistent(CacheNamespace::Logins)->get(self::key($ip));

		return is_array($record) && is_int($record['count'] ?? null) ? $record['count'] : 0;
	}

	/**
	 * Returns the address's key.
	 */
	private static function key(string $ip): string
	{
		return hash('sha256', 'token|' . $ip);
	}
}
