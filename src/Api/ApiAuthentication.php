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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\BearerTokens;
use Blush\Auth\Token;
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
	public function __construct(
		private ApiConfig $config,
		private ApiResponses $responses,
		private BearerTokens $bearer,
		private TokenThrottle $throttle
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

		if (! BearerTokens::isSecure($request)) {
			return $this->responses->error(ApiError::httpsRequired());
		}

		$ip = ClientIp::of($request);

		if ($this->throttle->isLockedOut($ip)) {
			return $this->responses->error(ApiError::tooManyAttempts());
		}

		// An app's token, from OAuth, works only on the MCP server (D-752).
		$found = $this->bearer->find($header);

		if ($found === null || $found[0]->client !== null) {
			$this->throttle->fail($ip);

			return $this->responses->error(ApiError::unauthorized('invalid_token', 'The token is wrong, revoked, or expired, or its account is suspended.'));
		}

		[$token, $account] = $found;

		return $handler->handle($request->withAttribute(Token::class, $this->bearer->used($token))->withAttribute(Account::class, $account));
	}
}
