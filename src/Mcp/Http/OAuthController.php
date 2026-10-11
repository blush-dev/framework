<?php

/**
 * OAuth controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\AuthException;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;
use Blush\Http\RateLimiter;
use Blush\Http\Status;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthError;
use Blush\Mcp\OAuth\OAuthServer;

/**
 * OAuth's addresses for the MCP server (D-752), answered while it's on:
 *
 * - `GET /.well-known/oauth-protected-resource` (and with the MCP
 *   server's path after it): the protected resource's metadata.
 * - `GET /.well-known/oauth-authorization-server`: the server's.
 * - `POST /oauth/register`: dynamic registration (RFC 7591), 20 an hour
 *   from an address.
 * - `POST /oauth/token`: codes and refresh tokens for tokens.
 * - `POST /oauth/revoke`: revoking one (RFC 7009).
 *
 * The authorization endpoint is the admin's Connect an App screen.
 */
final readonly class OAuthController
{
	/**
	 * How many registrations an address may make in an hour.
	 */
	private const int REGISTRATIONS = 20;

	public function __construct(
		private OAuthServer $server,
		private OAuthClients $clients,
		private RateLimiter $limiter
	) {}

	public function resource(): ResponseInterface
	{
		return McpResponses::json($this->server->resourceMetadata());
	}

	public function server(): ResponseInterface
	{
		return McpResponses::json($this->server->serverMetadata());
	}

	/**
	 * @throws CacheException
	 */
	public function register(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->limiter->hit('oauth|register|' . ClientIp::of($request), self::REGISTRATIONS, 3600) !== null) {
			return McpResponses::json(['error' => 'too_many_requests', 'error_description' => 'Too many registrations from this address; try again later.'], Status::TooManyRequests);
		}

		try {
			$metadata = json_decode((string) $request->getBody(), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$metadata = null;
		}

		if (! is_array($metadata)) {
			return McpResponses::json(['error' => 'invalid_client_metadata', 'error_description' => 'Send the client\'s metadata as a JSON object.'], Status::BadRequest);
		}

		try {
			$client = $this->clients->register($metadata);
		} catch (OAuthError $error) {
			return McpResponses::json($error->toArray(), Status::from($error->status));
		}

		return McpResponses::json([
			'client_id'                  => $client->clientId,
			'client_id_issued_at'        => $client->registered,
			'client_name'                => $client->name,
			'redirect_uris'              => $client->redirectUris,
			'grant_types'                => ['authorization_code', 'refresh_token'],
			'response_types'             => ['code'],
			'token_endpoint_auth_method' => 'none',
			...($client->uri === null ? [] : ['client_uri' => $client->uri])
		], Status::Created);
	}

	/**
	 * @throws AuthException
	 */
	public function token(ServerRequestInterface $request): ResponseInterface
	{
		try {
			return McpResponses::json($this->server->token(self::params($request)));
		} catch (OAuthError $error) {
			return McpResponses::json($error->toArray(), Status::from($error->status));
		}
	}

	/**
	 * @throws AuthException
	 */
	public function revoke(ServerRequestInterface $request): ResponseInterface
	{
		$token = self::params($request)['token'] ?? null;

		try {
			if (is_string($token)) {
				$this->server->revoke($token);
			}
		} catch (OAuthError $error) {
			return McpResponses::json($error->toArray(), Status::from($error->status));
		}

		return McpResponses::empty(Status::Ok);
	}

	public function preflight(): ResponseInterface
	{
		return McpResponses::preflight('GET, POST, OPTIONS');
	}

	/**
	 * The form a token request sends, or JSON.
	 *
	 * @return array<mixed>
	 */
	private static function params(ServerRequestInterface $request): array
	{
		$parsed = $request->getParsedBody();

		if (is_array($parsed) && $parsed !== []) {
			return $parsed;
		}

		$body = (string) $request->getBody();

		try {
			$json = json_decode($body, true, 16, JSON_THROW_ON_ERROR);

			if (is_array($json)) {
				return $json;
			}
		} catch (JsonException) {
		}

		parse_str($body, $form);

		return $form;
	}
}
