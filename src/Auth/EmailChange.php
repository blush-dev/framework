<?php

/**
 * Email change.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * A change of email address waiting on the new address to confirm it
 * (D-849): the site's (`for` is `EmailChanges::SITE`) or an account's
 * (`for` is its id), the address it changes to, when it expires, and who
 * asked. `hash` is the SHA-256 of the token in the link last emailed,
 * never the token, and `null` until one is: the link is made when its
 * email is sent (as a password link's is, D-846), so a token never sits
 * in a queued job.
 */
final readonly class EmailChange
{
	public function __construct(
		public string $for,
		public string $email,
		public int $expires,
		public ?string $by = null,
		public ?string $hash = null
	) {}

	/**
	 * Whether it's the site's address that changes.
	 */
	public function isSite(): bool
	{
		return $this->for === EmailChanges::SITE;
	}

	/**
	 * Whether a token is the one last emailed, and it hasn't expired.
	 */
	public function accepts(string $token, int $now): bool
	{
		return $this->hash !== null && $now < $this->expires && hash_equals($this->hash, hash('sha256', $token));
	}

	/**
	 * Returns a copy with a new link's token, and the token, which is
	 * emailed once and never kept.
	 *
	 * @return array{self, string}
	 */
	public function withNewToken(): array
	{
		$token = bin2hex(random_bytes(32));

		return [clone($this, ['hash' => hash('sha256', $token)]), $token];
	}

	/**
	 * Builds one from its stored array, or `null` when it isn't one.
	 */
	public static function fromArray(mixed $data): ?self
	{
		if (! is_array($data) || ! is_string($data['for'] ?? null) || ! is_string($data['email'] ?? null) || ! is_int($data['expires'] ?? null)) {
			return null;
		}

		return new self(
			$data['for'],
			$data['email'],
			$data['expires'],
			is_string($data['by'] ?? null) ? $data['by'] : null,
			is_string($data['hash'] ?? null) ? $data['hash'] : null
		);
	}

	/**
	 * Returns it as its stored array.
	 *
	 * @return array{for: string, email: string, expires: int, by: ?string, hash: ?string}
	 */
	public function toArray(): array
	{
		return ['for' => $this->for, 'email' => $this->email, 'expires' => $this->expires, 'by' => $this->by, 'hash' => $this->hash];
	}
}
