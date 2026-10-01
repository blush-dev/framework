<?php

/**
 * Password link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * A one-time link for choosing a password (D-312), as an account keeps
 * it: the SHA-256 of its token, never the token, and when it expires.
 * An administrator makes one for a new account, or for someone who
 * forgot their password, and sends it however they like; Blush sends no
 * email. Using it, or making another, ends it.
 */
final readonly class PasswordLink
{
	public function __construct(
		public string $hash,
		public int $expires
	) {}

	/**
	 * Makes a link that lasts a number of seconds, and returns it with
	 * its token, which is shown once and never kept.
	 *
	 * @return array{self, string}
	 */
	public static function make(int $now, int $lifetime): array
	{
		$token = bin2hex(random_bytes(32));

		return [new self(hash('sha256', $token), $now + $lifetime), $token];
	}

	/**
	 * Whether a token is this link's, and the link hasn't expired.
	 */
	public function accepts(string $token, int $now): bool
	{
		return $now < $this->expires && hash_equals($this->hash, hash('sha256', $token));
	}

	/**
	 * Builds a link from its stored array, or `null` when it isn't one.
	 *
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data): ?self
	{
		return is_string($data['hash'] ?? null) && is_int($data['expires'] ?? null)
			? new self($data['hash'], $data['expires'])
			: null;
	}

	/**
	 * Returns the link as its stored array.
	 *
	 * @return array{hash: string, expires: int}
	 */
	public function toArray(): array
	{
		return ['hash' => $this->hash, 'expires' => $this->expires];
	}
}
