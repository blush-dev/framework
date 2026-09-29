<?php

/**
 * Passwords.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Hashes and checks passwords with PHP's `password_*()`: Argon2id when PHP
 * has it, bcrypt otherwise. A hash made with older settings is flagged for
 * rehashing at the next sign-in.
 */
final class Passwords
{
	/**
	 * A hash to check against when there's no account, so a sign-in takes
	 * as long whether or not the username exists.
	 */
	private ?string $dummy = null;

	/**
	 * Returns the algorithm used for new hashes.
	 */
	public function algorithm(): string
	{
		return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
	}

	/**
	 * Hashes a password.
	 */
	public function hash(string $password): string
	{
		return password_hash($password, $this->algorithm());
	}

	/**
	 * Whether a password matches a hash. With no hash, it checks against a
	 * dummy one and returns `false`, taking as long as a real check.
	 */
	public function verify(string $password, ?string $hash): bool
	{
		if ($hash === null) {
			password_verify($password, $this->dummy ??= $this->hash(bin2hex(random_bytes(16))));

			return false;
		}

		return password_verify($password, $hash);
	}

	/**
	 * Whether a hash should be remade with the current settings.
	 */
	public function needsRehash(string $hash): bool
	{
		return password_needs_rehash($hash, $this->algorithm());
	}
}
