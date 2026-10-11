<?php

/**
 * OAuth grant.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use NoDiscard;
use Blush\Auth\TokenScope;

/**
 * A person's approval of an app (D-752): which account, which client (by
 * its record's id), what it may do (capabilities, or `null` for all the
 * account's), when it was approved, and when it last connected. One per
 * account and app; the API Tokens panel lists them as connected apps.
 */
final readonly class OAuthGrant
{
	/**
	 * @param ?list<string> $capabilities
	 */
	public function __construct(
		public string $id,
		public string $account,
		public string $client,
		public ?array $capabilities,
		public int $approved,
		public ?int $connected = null
	) {}

	/**
	 * The scope its capabilities match.
	 */
	public function scope(): TokenScope
	{
		return TokenScope::of($this->capabilities);
	}

	#[NoDiscard]
	public function withConnected(int $time): self
	{
		return clone($this, ['connected' => $time]);
	}

	/**
	 * @param  array<mixed> $data
	 * @throws OAuthError When it isn't a grant.
	 */
	public static function fromArray(array $data, string $id): self
	{
		$capabilities = $data['capabilities'] ?? null;

		if (! is_string($data['account'] ?? null) || ! is_string($data['client'] ?? null) || ($capabilities !== null && ! is_array($capabilities))) {
			throw new OAuthError('server_error', sprintf('The connected app "%s" is damaged.', $id), false, 500);
		}

		return new self(
			id: $id,
			account: $data['account'],
			client: $data['client'],
			capabilities: $capabilities === null ? null : array_values(array_filter($capabilities, is_string(...))),
			approved: is_int($data['approved'] ?? null) ? $data['approved'] : 0,
			connected: is_int($data['connected'] ?? null) ? $data['connected'] : null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'account'      => $this->account,
			'client'       => $this->client,
			'capabilities' => $this->capabilities,
			'approved'     => $this->approved,
			'connected'    => $this->connected
		];
	}
}
