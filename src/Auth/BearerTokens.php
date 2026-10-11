<?php

/**
 * Bearer tokens.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Finds the API token a request sends as `Authorization: Bearer`
 * (D-747), for the API and the MCP server alike: one that's right, not
 * expired, whose account may still use it (not suspended, D-748).
 * Whether the token may be used where it's sent is the caller's: an
 * OAuth client's works only on the MCP server (D-752).
 */
final readonly class BearerTokens
{
	/**
	 * Addresses a token may be sent to over plain HTTP: the site's own
	 * computer, and none, for a request made inside Blush (the CLI, a
	 * test).
	 *
	 * @var list<string>
	 */
	private const array LOCAL = ['', 'localhost', '127.0.0.1', '[::1]', '::1'];

	public function __construct(
		private Tokens $tokens,
		private Accounts $accounts,
		private ClockInterface $clock
	) {}

	/**
	 * Returns the token an `Authorization` header sends, with its
	 * account, when both may be used, or `null`.
	 *
	 * @return ?array{Token, Account}
	 * @throws AuthException When a record is damaged.
	 */
	public function find(string $header): ?array
	{
		if (preg_match('/\ABearer\s+(\S+)\z/i', trim($header), $matches) !== 1) {
			return null;
		}

		$token   = $this->tokens->verify($matches[1]);
		$account = $token === null || $token->isExpired($this->clock->now()->getTimestamp()) ? null : $this->accounts->findById($token->account);

		return $token === null || $account === null || $account->suspended ? null : [$token, $account];
	}

	/**
	 * Writes that a token was used, at most hourly; not writing it
	 * doesn't stop it being used.
	 */
	public function used(Token $token): Token
	{
		try {
			return $this->tokens->used($token);
		} catch (AuthException) {
			return $token;
		}
	}

	/**
	 * Whether a request came over HTTPS, or to the site's own computer.
	 */
	public static function isSecure(ServerRequestInterface $request): bool
	{
		$uri = $request->getUri();

		return $uri->getScheme() === 'https' || in_array(strtolower($uri->getHost()), self::LOCAL, true);
	}
}
