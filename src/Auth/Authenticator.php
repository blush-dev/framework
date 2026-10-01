<?php

/**
 * Authenticator.
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
use Blush\Session\Session;

/**
 * Signs accounts in and out of sessions (D-219).
 *
 * - `attempt()` checks a username and password, throttled by address
 *   (`LoginThrottle`), and takes as long for an unknown username as for a
 *   known one. A success rehashes an old hash and records the time.
 * - `login()` gives the session a new id (against session fixation) and
 *   stores the username, a fingerprint of the password hash, and a new
 *   CSRF token.
 * - `confirm()` checks a signed-in account's password again (before
 *   changing it), throttled like a sign-in.
 * - `refresh()` keeps a session signed in after its own account's
 *   password changed, with a new id.
 * - `account()` returns the session's account, or `null` when it was
 *   removed, suspended, or its password changed since, which signs out
 *   every other session when a password changes. It forgets such a
 *   stale sign-in.
 *
 * A suspended account can't sign in (D-312).
 */
final readonly class Authenticator
{
	/**
	 * The session key for the signed-in username.
	 */
	public const string ACCOUNT = 'auth.account';

	/**
	 * The session key for the password hash's fingerprint.
	 */
	public const string FINGERPRINT = 'auth.fingerprint';

	/**
	 * The session key for the CSRF token.
	 */
	public const string CSRF = 'auth.csrf';

	public function __construct(
		private AccountStore $accounts,
		private Passwords $passwords,
		private LoginThrottle $throttle,
		private ClockInterface $clock
	) {}

	/**
	 * Returns the account a username and password sign in, or `null`.
	 *
	 * @throws LockedOut        After too many failures.
	 * @throws AccountSuspended For the right password to a suspended account.
	 * @throws AuthException    When the account's record is damaged.
	 * @throws CacheException
	 */
	public function attempt(string $username, string $password, string $ip): ?Account
	{
		$username = strtolower(trim($username));

		if ($this->throttle->isLockedOut($ip, $username)) {
			throw new LockedOut('Too many failed sign-ins. Try again later.');
		}

		$account = $this->accounts->find($username);

		if (! $this->passwords->verify($password, $account?->passwordHash) || $account === null) {
			$this->throttle->fail($ip, $username);

			return null;
		}

		$this->throttle->clear($ip, $username);

		if ($account->suspended) {
			throw new AccountSuspended('This account is suspended. Ask an administrator to reinstate it.');
		}

		if ($this->passwords->needsRehash($account->passwordHash)) {
			$account = $account->withPasswordHash($this->passwords->hash($password));
		}

		$account = $account->withLastLogin($this->clock->now()->getTimestamp());
		$this->accounts->save($account);

		return $account;
	}

	/**
	 * Whether a password is an account's, throttled like a sign-in: the
	 * account is signed in already, so this only guards a sensitive
	 * change (a new password) against a borrowed session.
	 *
	 * @throws LockedOut After too many failures.
	 * @throws CacheException
	 */
	public function confirm(Account $account, string $password, string $ip): bool
	{
		if ($this->throttle->isLockedOut($ip, $account->username)) {
			throw new LockedOut('Too many wrong passwords. Try again later.');
		}

		if (! $this->passwords->verify($password, $account->passwordHash)) {
			$this->throttle->fail($ip, $account->username);

			return false;
		}

		$this->throttle->clear($ip, $account->username);

		return true;
	}

	/**
	 * Keeps a session signed in to an account whose password it just
	 * changed: a new session id and the new hash's fingerprint. The CSRF
	 * token stays, so the admin's next request still has it.
	 */
	public function refresh(Session $session, Account $account): void
	{
		$session->regenerate();
		$session->set(self::FINGERPRINT, self::fingerprint($account));
	}

	/**
	 * Signs an account in to a session.
	 */
	public function login(Session $session, Account $account): void
	{
		$session->regenerate();
		$session->set(self::ACCOUNT, $account->username);
		$session->set(self::FINGERPRINT, self::fingerprint($account));
		$session->set(self::CSRF, bin2hex(random_bytes(32)));
	}

	/**
	 * Signs the session out, ending it.
	 */
	public function logout(Session $session): void
	{
		$session->invalidate();
	}

	/**
	 * Returns the session's account, or `null`. A session signed in to an
	 * account that's gone, suspended, or whose password changed is signed
	 * out.
	 *
	 * @throws AuthException When the account's record is damaged.
	 */
	public function account(Session $session): ?Account
	{
		$username    = $session->get(self::ACCOUNT);
		$fingerprint = $session->get(self::FINGERPRINT);

		if (! is_string($username) || ! is_string($fingerprint)) {
			return null;
		}

		$account = $this->accounts->find($username);

		if ($account !== null && ! $account->suspended && hash_equals(self::fingerprint($account), $fingerprint)) {
			return $account;
		}

		// Signed out elsewhere: forget the sign-in and its CSRF token, so
		// this browser can sign in again.
		$session->remove(self::ACCOUNT);
		$session->remove(self::FINGERPRINT);
		$session->remove(self::CSRF);

		return null;
	}

	/**
	 * Returns the session's CSRF token, or `null` when it's signed out.
	 */
	public function csrfToken(Session $session): ?string
	{
		$token = $session->get(self::CSRF);

		return is_string($token) ? $token : null;
	}

	/**
	 * Returns a fingerprint of an account's password hash, which changes
	 * with the password.
	 */
	private static function fingerprint(Account $account): string
	{
		return hash('sha256', $account->passwordHash);
	}
}
