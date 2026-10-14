<?php

/**
 * Password resets.
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
 * Keeps "Forgot your password?" (D-846) from becoming a way to flood
 * someone's inbox, in the persistent `logins` store beside the sign-in
 * throttle: an address may ask `AuthConfig::$maxAttempts` times within
 * `lockout` seconds, whichever accounts it names, and an account is
 * sent at most one link every `COOLDOWN` seconds, whoever asks.
 */
final readonly class PasswordResets
{
	/**
	 * The fewest seconds between two links sent to one account.
	 */
	public const int COOLDOWN = 300;

	public function __construct(
		private Caches $caches,
		private AuthConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Whether an address has asked too often, counting this time.
	 *
	 * @throws CacheException
	 */
	public function isLockedOut(string $ip): bool
	{
		$store  = $this->caches->persistent(CacheNamespace::Logins);
		$key    = hash('sha256', 'reset-ip|' . $ip);
		$now    = $this->clock->now()->getTimestamp();
		$record = $store->get($key);
		$count  = is_array($record) && is_int($record['count'] ?? null) ? $record['count'] : 0;
		$since  = is_array($record) && is_int($record['since'] ?? null) ? $record['since'] : $now;

		$store->set($key, ['count' => $count + 1, 'since' => $since], max(1, $since + $this->config->lockout - $now));

		return $count >= $this->config->maxAttempts;
	}

	/**
	 * Whether an account may be sent a link now, and if so, counts it as
	 * sent, so the next `COOLDOWN` seconds' asks are skipped.
	 *
	 * @throws CacheException
	 */
	public function claim(Account $account): bool
	{
		$store = $this->caches->persistent(CacheNamespace::Logins);
		$key   = hash('sha256', 'reset-account|' . $account->id);

		if ($store->get($key) !== null) {
			return false;
		}

		$store->set($key, $this->clock->now()->getTimestamp(), self::COOLDOWN);

		return true;
	}
}
