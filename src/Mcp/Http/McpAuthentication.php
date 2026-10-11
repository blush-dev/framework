<?php

/**
 * MCP authentication.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Http;

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
use Blush\Http\Status;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthError;
use Blush\Mcp\OAuth\OAuthServer;

/**
 * Lets only a request with a token through to the MCP server (D-754): an
 * API token (D-746) or an app's, from OAuth (D-752), sent as
 * `Authorization: Bearer` over HTTPS. One without is a `401` whose
 * `WWW-Authenticate` names the protected resource's metadata, which is
 * how an app finds where to sign in (RFC 9728). Bad tokens are throttled
 * by address, as the API's are (D-750).
 *
 * The request gets the token, its account, and, for an app's, the app's
 * name (`client`), for the activity log.
 */
final readonly class McpAuthentication implements MiddlewareInterface
{
	/**
	 * The request attribute an app's name is in.
	 */
	public const string CLIENT = 'blush.mcp.client';

	public function __construct(
		private BearerTokens $bearer,
		private TokenThrottle $throttle,
		private OAuthServer $oauth,
		private OAuthClients $clients
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

		$header = trim($request->getHeaderLine('Authorization'));

		if ($header === '') {
			return $this->unauthorized('token_required', 'This MCP server needs a token: connect the app to sign in, or send an API token as "Bearer {token}".');
		}

		if (! BearerTokens::isSecure($request)) {
			return McpResponses::json(['error' => 'https_required', 'error_description' => 'Tokens are sent only over HTTPS.'], Status::Forbidden);
		}

		$ip = ClientIp::of($request);

		if ($this->throttle->isLockedOut($ip)) {
			return McpResponses::json(['error' => 'too_many_attempts', 'error_description' => 'Too many wrong tokens from this address; try again later.'], Status::TooManyRequests);
		}

		$found = $this->bearer->find($header);

		if ($found === null) {
			$this->throttle->fail($ip);

			return $this->unauthorized('invalid_token', 'The token is wrong, revoked, or expired, or its account is suspended.');
		}

		[$token, $account] = $found;

		try {
			$client = $token->client === null ? null : $this->clients->byId($token->client)?->name;
		} catch (OAuthError) {
			$client = null;
		}

		return $handler->handle($request
			->withAttribute(Token::class, $this->bearer->used($token))
			->withAttribute(Account::class, $account)
			->withAttribute(self::CLIENT, $client));
	}

	/**
	 * A `401` naming where to sign in.
	 */
	private function unauthorized(string $error, string $description): ResponseInterface
	{
		$challenge = sprintf('Bearer resource_metadata="%s"%s', $this->oauth->resourceMetadataUrl(), $error === 'invalid_token' ? ', error="invalid_token"' : '');

		return McpResponses::json(['error' => $error, 'error_description' => $description], Status::Unauthorized, ['WWW-Authenticate' => $challenge]);
	}
}
