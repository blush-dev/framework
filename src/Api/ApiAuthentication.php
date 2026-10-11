<?php

/**
 * API authentication.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Override;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Token;
use Blush\Auth\Tokens;
use Blush\Auth\TokenThrottle;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;

/**
 * Finds the API token a request sends, for every route under the API
 * (D-744 to D-750). A request with a good one goes on with the `Token`
 * and its `Account` as attributes; `ApiResponses` then keeps its answer
 * private, and a resource may show it what its account may edit.
 *
 * - **Where:** `Authorization: Bearer {token}`, only. A token in the
 *   address (`access_token` or `token`) is refused, since addresses end
 *   up in logs.
 * - **HTTPS:** a token over plain HTTP is refused, but on the site's own
 *   computer (`localhost`, `127.0.0.1`, `::1`).
 * - **Good:** its secret is right, it hasn't expired, and its account
 *   exists and isn't suspended, checked on every request (D-744). Its
 *   use is written at most hourly.
 * - **Not:** a `401` (`invalid_token`), counted against the address
 *   (`TokenThrottle`); past the limit, a `429` until it passes.
 * - **None:** the request goes on anonymously, unless the API is for
 *   requests with a token only (`ApiAccess::Token`), when it's a `401`
 *   (`token_required`).
 *
 * A session cookie means nothing here, so a token decides what a
 * request may see whether or not its browser is signed in to the admin
 * (D-746). CORS preflights go through untouched.
 */
final readonly class ApiAuthentication implements MiddlewareInterface
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
		private ApiConfig $config,
		private ApiResponses $responses,
		private Tokens $tokens,
		private Accounts $accounts,
		private TokenThrottle $throttle,
		private ClockInterface $clock
	) {}

	/**
	 * @inheritDoc
	 * @throws AuthException When a token's or account's record is damaged.
	 * @throws CacheException
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if ($request->getMethod() === 'OPTIONS') {
			return $handler->handle($request);
		}

		$params = $request->getQueryParams();

		if (array_key_exists('access_token', $params) || array_key_exists('token', $params)) {
			return $this->responses->error(ApiError::invalidQuery('Send a token in the Authorization header, as "Bearer {token}", never in the address.'));
		}

		$header = trim($request->getHeaderLine('Authorization'));

		if ($header === '') {
			return $this->config->access === ApiAccess::Token
				? $this->responses->error(ApiError::unauthorized('token_required', 'This API needs a token, sent in the Authorization header as "Bearer {token}".'))
				: $handler->handle($request);
		}

		if (! self::isSecure($request)) {
			return $this->responses->error(ApiError::httpsRequired());
		}

		$ip = ClientIp::of($request);

		if ($this->throttle->isLockedOut($ip)) {
			return $this->responses->error(ApiError::tooManyAttempts());
		}

		$found = $this->find($header);

		if ($found === null) {
			$this->throttle->fail($ip);

			return $this->responses->error(ApiError::unauthorized('invalid_token', 'The token is wrong, revoked, or expired, or its account is suspended.'));
		}

		[$token, $account] = $found;

		try {
			$token = $this->tokens->used($token);
		} catch (AuthException) {
			// Not writing when it was used doesn't stop it being used.
		}

		return $handler->handle($request->withAttribute(Token::class, $token)->withAttribute(Account::class, $account));
	}

	/**
	 * Returns the token an `Authorization` header sends, with its
	 * account, when both may be used, or `null`.
	 *
	 * @return ?array{Token, Account}
	 * @throws AuthException When a record is damaged.
	 */
	private function find(string $header): ?array
	{
		if (preg_match('/\ABearer\s+(\S+)\z/i', $header, $matches) !== 1) {
			return null;
		}

		$token   = $this->tokens->verify($matches[1]);
		$account = $token === null || $token->isExpired($this->clock->now()->getTimestamp()) ? null : $this->accounts->findById($token->account);

		return $token === null || $account === null || $account->suspended ? null : [$token, $account];
	}

	/**
	 * Whether a request came over HTTPS, or to the site's own computer.
	 */
	private static function isSecure(ServerRequestInterface $request): bool
	{
		$uri = $request->getUri();

		return $uri->getScheme() === 'https' || in_array(strtolower($uri->getHost()), self::LOCAL, true);
	}
}
