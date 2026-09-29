<?php

/**
 * Login throttle.
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
 * Counts failed sign-ins in the persistent `logins` store, so guessing
 * passwords is slow. Two counters apply at once: one for the address and
 * username together (`AuthConfig::$maxAttempts`), and one for the address
 * alone at four times that, against one address trying many usernames.
 * Either one past its limit locks sign-ins out until `lockout` seconds
 * after the first failure it counted. A success clears the pair's counter.
 *
 * Counters are read and written without a lock, so parallel requests can
 * slip a few extra guesses in; that's acceptable for a counter measured
 * in single digits.
 */
final readonly class LoginThrottle
{
	public function __construct(
		private Caches $caches,
		private AuthConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Whether sign-ins from an address for a username are locked out.
	 *
	 * @throws CacheException
	 */
	public function isLockedOut(string $ip, string $username): bool
	{
		return $this->count($this->pairKey($ip, $username)) >= $this->config->maxAttempts
			|| $this->count($this->ipKey($ip)) >= $this->config->maxAttempts * 4;
	}

	/**
	 * Records a failed sign-in.
	 *
	 * @throws CacheException
	 */
	public function fail(string $ip, string $username): void
	{
		foreach ([$this->pairKey($ip, $username), $this->ipKey($ip)] as $key) {
			$this->increment($key);
		}
	}

	/**
	 * Clears the address and username's failures after a success.
	 *
	 * @throws CacheException
	 */
	public function clear(string $ip, string $username): void
	{
		$this->caches->persistent(CacheNamespace::Logins)->delete($this->pairKey($ip, $username));
	}

	/**
	 * Returns a counter's failures within its window.
	 *
	 * @throws CacheException
	 */
	private function count(string $key): int
	{
		$record = $this->caches->persistent(CacheNamespace::Logins)->get($key);

		return is_array($record) && is_int($record['count'] ?? null) ? $record['count'] : 0;
	}

	/**
	 * Adds a failure to a counter, keeping its window's start.
	 *
	 * @throws CacheException
	 */
	private function increment(string $key): void
	{
		$store  = $this->caches->persistent(CacheNamespace::Logins);
		$now    = $this->clock->now()->getTimestamp();
		$record = $store->get($key);
		$since  = is_array($record) && is_int($record['since'] ?? null) ? $record['since'] : $now;

		$store->set($key, ['count' => $this->count($key) + 1, 'since' => $since], max(1, $since + $this->config->lockout - $now));
	}

	/**
	 * Returns the address and username's key.
	 */
	private function pairKey(string $ip, string $username): string
	{
		return hash('sha256', 'pair|' . $ip . '|' . strtolower($username));
	}

	/**
	 * Returns the address's key.
	 */
	private function ipKey(string $ip): string
	{
		return hash('sha256', 'ip|' . $ip);
	}
}
