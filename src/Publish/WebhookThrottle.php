<?php

/**
 * Webhook throttle.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Psr\Clock\ClockInterface;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;

/**
 * Counts an address's webhook requests that fail the signature check, in
 * the persistent `webhooks` store, so guessing at the secret, or flooding
 * the endpoint, is slow. `PublishConfig::$maxAttempts` failures lock the
 * address out until `lockout` seconds after the first one it counted. A
 * signed request clears the address's counter.
 *
 * As with `LoginThrottle`, the counter is read and written without a
 * lock, so parallel requests can slip a few extra tries in.
 */
final readonly class WebhookThrottle
{
	public function __construct(
		private Caches $caches,
		private PublishConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Whether requests from an address are locked out.
	 *
	 * @throws CacheException
	 */
	public function isLockedOut(string $ip): bool
	{
		return $this->count($ip) >= $this->config->maxAttempts;
	}

	/**
	 * Records a request that failed the signature check.
	 *
	 * @throws CacheException
	 */
	public function fail(string $ip): void
	{
		$store  = $this->caches->persistent(CacheNamespace::Webhooks);
		$now    = $this->clock->now()->getTimestamp();
		$record = $store->get(self::key($ip));
		$since  = is_array($record) && is_int($record['since'] ?? null) ? $record['since'] : $now;

		$store->set(self::key($ip), ['count' => $this->count($ip) + 1, 'since' => $since], max(1, $since + $this->config->lockout - $now));
	}

	/**
	 * Clears an address's failures after a signed request.
	 *
	 * @throws CacheException
	 */
	public function clear(string $ip): void
	{
		$this->caches->persistent(CacheNamespace::Webhooks)->delete(self::key($ip));
	}

	/**
	 * Returns an address's failures within its window.
	 *
	 * @throws CacheException
	 */
	private function count(string $ip): int
	{
		$record = $this->caches->persistent(CacheNamespace::Webhooks)->get(self::key($ip));

		return is_array($record) && is_int($record['count'] ?? null) ? $record['count'] : 0;
	}

	/**
	 * Returns an address's key, apart from the signatures the store keeps.
	 */
	private static function key(string $ip): string
	{
		return hash('sha256', 'fail|' . $ip);
	}
}
