<?php

/**
 * OAuth secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use NoDiscard;

/**
 * A short-lived secret OAuth hands out (D-752), kept only as a hash: an
 * authorization `code` (ten minutes, once, bound to the address it was
 * sent to and the PKCE challenge), or a `refresh` token (90 days,
 * replaced each time it's used; used again, it revokes the app).
 */
final readonly class OAuthSecret
{
	public const string CODE = 'code';

	public const string REFRESH = 'refresh';

	public function __construct(
		public string $id,
		public string $kind,
		public string $grant,
		public string $hash,
		public int $expires,
		public bool $used = false,
		public string $redirectUri = '',
		public string $challenge = ''
	) {}

	/**
	 * Whether a secret is this one's.
	 */
	public function accepts(string $secret): bool
	{
		return hash_equals($this->hash, hash('sha256', $secret));
	}

	#[NoDiscard]
	public function withUsed(): self
	{
		return clone($this, ['used' => true]);
	}

	/**
	 * @param  array<mixed> $data
	 * @throws OAuthError When it isn't one.
	 */
	public static function fromArray(array $data, string $id): self
	{
		if (! is_string($data['kind'] ?? null) || ! is_string($data['grant'] ?? null) || ! is_string($data['hash'] ?? null) || ! is_int($data['expires'] ?? null)) {
			throw new OAuthError('server_error', 'An OAuth secret is damaged.', false, 500);
		}

		return new self(
			id: $id,
			kind: $data['kind'],
			grant: $data['grant'],
			hash: $data['hash'],
			expires: $data['expires'],
			used: ($data['used'] ?? false) === true,
			redirectUri: is_string($data['redirectUri'] ?? null) ? $data['redirectUri'] : '',
			challenge: is_string($data['challenge'] ?? null) ? $data['challenge'] : ''
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'kind'        => $this->kind,
			'grant'       => $this->grant,
			'hash'        => $this->hash,
			'expires'     => $this->expires,
			'used'        => $this->used,
			'redirectUri' => $this->redirectUri,
			'challenge'   => $this->challenge
		];
	}
}
