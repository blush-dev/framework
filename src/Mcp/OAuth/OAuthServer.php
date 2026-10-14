<?php

/**
 * OAuth server.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use Psr\Clock\ClockInterface;
use Blush\Admin\AdminConfig;
use Blush\Api\ApiConfig;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Tokens;
use Blush\Auth\TokenScope;
use Blush\Core\AppConfig;
use Blush\Translation\Messages;

/**
 * OAuth 2.1 for the MCP server (D-752): Blush is its own authorization
 * server, for its own accounts.
 *
 * - **Discovery:** the protected resource's metadata (RFC 9728) and the
 *   authorization server's (RFC 8414).
 * - **Authorization code with PKCE (S256 only):** the person approves an
 *   app on the admin's Connect an App screen (`/admin/authorize`), which
 *   asks `describe()`, then `approve()` or `deny()`. A code lasts ten
 *   minutes and is used once.
 * - **Tokens bound to the MCP server** (RFC 8707's `resource`): an access
 *   token is an API token (D-747) with its `client` set, lasting an hour,
 *   working only on the MCP server; each comes with a refresh token of
 *   90 days, replaced whenever it's used. A refresh token used twice
 *   revokes every token of that app for that account, since it leaked.
 * - **Scopes** are the token presets: `read`, `drafts`, and `account`
 *   (`TokenScope`); a person may narrow what an app asks for, never
 *   widen it, and approving takes `api.connect`.
 */
final readonly class OAuthServer
{
	public const int CODE_LIFETIME = 600;

	public const int ACCESS_LIFETIME = 3600;

	public const int REFRESH_LIFETIME = 90 * 86400;

	public function __construct(
		private OAuthClients $clients,
		private OAuthGrants $grants,
		private Tokens $tokens,
		private Accounts $accounts,
		private Permissions $permissions,
		private AppConfig $app,
		private ApiConfig $api,
		private AdminConfig $admin,
		private ClockInterface $clock,
		private Messages $messages
	) {}

	/**
	 * The MCP server's address: the resource its tokens are for.
	 */
	public function resource(): string
	{
		return $this->app->origin() . $this->api->path . '/mcp';
	}

	/**
	 * Where the protected resource's metadata is (RFC 9728): the
	 * well-known address with the resource's path after it.
	 */
	public function resourceMetadataUrl(): string
	{
		return $this->app->origin() . '/.well-known/oauth-protected-resource' . $this->api->path . '/mcp';
	}

	/**
	 * The protected resource's metadata (RFC 9728).
	 *
	 * @return array<string, mixed>
	 */
	public function resourceMetadata(): array
	{
		return [
			'resource'                 => $this->resource(),
			'authorization_servers'    => [$this->app->origin()],
			'scopes_supported'         => array_column(TokenScope::cases(), 'value'),
			'bearer_methods_supported' => ['header'],
			'resource_name'            => $this->app->name
		];
	}

	/**
	 * The authorization server's metadata (RFC 8414).
	 *
	 * @return array<string, mixed>
	 */
	public function serverMetadata(): array
	{
		$origin = $this->app->origin();

		return [
			'issuer'                                => $origin,
			'authorization_endpoint'                => $origin . $this->admin->path . '/authorize',
			'token_endpoint'                        => "{$origin}/oauth/token",
			'registration_endpoint'                 => "{$origin}/oauth/register",
			'revocation_endpoint'                   => "{$origin}/oauth/revoke",
			'scopes_supported'                      => array_column(TokenScope::cases(), 'value'),
			'response_types_supported'              => ['code'],
			'response_modes_supported'              => ['query'],
			'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
			'code_challenge_methods_supported'      => ['S256'],
			'token_endpoint_auth_methods_supported' => ['none'],
			'client_id_metadata_document_supported' => true
		];
	}

	/**
	 * Checks an authorization request (RFC 6749 §4.1.1, with PKCE), and
	 * returns it. An error about the client or its redirect address isn't
	 * `redirectable`: it's shown, never sent to the address.
	 *
	 * @param  array<mixed> $params
	 * @throws OAuthError
	 */
	public function authorization(array $params): AuthorizationRequest
	{
		$clientId = $params['client_id'] ?? null;
		$redirect = $params['redirect_uri'] ?? null;
		$client   = is_string($clientId) && $clientId !== '' ? $this->clients->find($clientId) : null;

		if ($client === null) {
			throw OAuthError::client((string) $this->messages->t('mcp.oauth_server.unknown_app', 'The app asking to connect isn\'t one this site knows. Start connecting again from the app.'));
		}

		if (! is_string($redirect) || ! $client->allowsRedirect($redirect)) {
			throw new OAuthError('invalid_request', (string) $this->messages->t('mcp.oauth_server.unregistered_redirect', '{app} asked to send you back to an address it didn\'t register, so it isn\'t sent there.', app: $client->name), redirectable: false);
		}

		$state = is_string($params['state'] ?? null) ? $params['state'] : null;
		$base  = new AuthorizationRequest($client, $redirect, $state, '', TokenScope::Account, null);

		if (($params['response_type'] ?? null) !== 'code') {
			throw $base->error('unsupported_response_type', 'Only the authorization code flow is taken.');
		}

		$challenge = $params['code_challenge'] ?? null;

		if (! is_string($challenge) || preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) !== 1 || ($params['code_challenge_method'] ?? null) !== 'S256') {
			throw $base->error('invalid_request', 'PKCE with S256 is required: send "code_challenge" and "code_challenge_method": "S256".');
		}

		$resource = $params['resource'] ?? null;

		if ($resource !== null && (! is_string($resource) || rtrim($resource, '/') !== $this->resource())) {
			throw $base->error('invalid_target', sprintf('Tokens here are for %s alone.', $this->resource()));
		}

		return new AuthorizationRequest($client, $redirect, $state, $challenge, self::scopeOf($params['scope'] ?? null) ?? throw $base->error('invalid_scope', 'Ask for "read", "drafts", or "account".'), is_string($resource) ? $resource : null);
	}

	/**
	 * Whether an account may approve apps: `api.connect` (D-752).
	 */
	public function mayConnect(Account $account): bool
	{
		return ! $account->suspended && ! $account->tokenOnly && $this->permissions->can($account, Capability::ApiConnect);
	}

	/**
	 * Returns the address to send someone back to at once when they've
	 * approved the app before with the same or a wider choice, or `null`
	 * when they're to be asked.
	 *
	 * @throws OAuthError
	 */
	public function approvedBefore(Account $account, AuthorizationRequest $request): ?string
	{
		$grant = $this->grants->find($account->id, $request->client->id);

		if ($grant === null || ! $this->mayConnect($account) || ! $grant->scope()->covers($request->scope) || ($grant->scope() === TokenScope::Account && $grant->capabilities !== null)) {
			return null;
		}

		$capabilities = $request->scope === $grant->scope() ? $grant->capabilities : $request->scope->capabilities();

		return $this->approve($account, $request, $capabilities);
	}

	/**
	 * Approves an app for an account, with what it may do (capabilities,
	 * or `null` for all the account's), within what it asked for and what
	 * the account has, and returns the address to send the person back to,
	 * with a code.
	 *
	 * @param  ?list<string> $capabilities
	 * @throws OAuthError
	 */
	public function approve(Account $account, AuthorizationRequest $request, ?array $capabilities): string
	{
		if (! $this->mayConnect($account)) {
			throw $request->error('access_denied', (string) $this->messages->t('mcp.oauth_server.may_not_connect', 'This account may not connect apps.'));
		}

		if (! $request->scope->covers(TokenScope::of($capabilities)) || ($request->scope !== TokenScope::Account && $capabilities === null)) {
			throw $request->error('invalid_scope', (string) $this->messages->t('mcp.oauth_server.too_wide', 'The choice is wider than the app asked for.'));
		}

		$asked = $request->scope->capabilities();
		$held     = $this->permissions->capabilities($account);

		foreach ($capabilities ?? [] as $capability) {
			if ($asked !== null && ! in_array($capability, $asked, true)) {
				throw $request->error('invalid_scope', (string) $this->messages->t('mcp.oauth_server.more_than_asked', '"{capability}" is more than the app asked for.', capability: $capability));
			}

			if (! in_array($capability, $held, true) && ! in_array($capability, $asked ?? [], true)) {
				throw $request->error('invalid_scope', (string) $this->messages->t('mcp.oauth_server.not_held', '"{capability}" isn\'t a capability this account has.', capability: $capability));
			}
		}

		$client = $this->clients->approve($request->client);
		$grant  = $this->grants->find($account->id, $client->id);
		$grant  = $this->grants->save(new OAuthGrant(
			id: $grant->id ?? $this->grants->newId(),
			account: $account->id,
			client: $client->id,
			capabilities: $capabilities,
			approved: $this->clock->now()->getTimestamp(),
			connected: $grant?->connected
		));

		$code = $this->grants->issue(OAuthSecret::CODE, $grant->id, self::CODE_LIFETIME, $request->redirectUri, $request->challenge);

		return $request->redirectTo(['code' => $code, 'iss' => $this->app->origin()]);
	}

	/**
	 * Returns the address to send the person back to when they deny an
	 * app.
	 */
	public function deny(AuthorizationRequest $request): string
	{
		return $request->redirectTo(['error' => 'access_denied', 'error_description' => 'The person didn\'t connect the app.', 'iss' => $this->app->origin()]);
	}

	/**
	 * Answers the token endpoint (RFC 6749 §3.2): a code for tokens, or a
	 * refresh token for new ones.
	 *
	 * @param  array<mixed>         $params
	 * @return array<string, mixed>
	 * @throws OAuthError
	 * @throws AuthException
	 */
	public function token(array $params): array
	{
		return match ($params['grant_type'] ?? null) {
			'authorization_code' => $this->exchange($params),
			'refresh_token'      => $this->refresh($params),
			default              => throw new OAuthError('unsupported_grant_type', 'Send "grant_type": "authorization_code" or "refresh_token".')
		};
	}

	/**
	 * Revokes a refresh token or an access token (RFC 7009); an unknown
	 * one is nothing to revoke.
	 *
	 * @throws OAuthError
	 * @throws AuthException
	 */
	public function revoke(string $text): void
	{
		$secret = $this->grants->secret($text, OAuthSecret::REFRESH);
		$grant  = $secret === null ? null : $this->grants->byId($secret->grant);

		if ($grant !== null) {
			$this->disconnect($grant);

			return;
		}

		$token = $this->tokens->verify($text);

		if ($token !== null && $token->client !== null) {
			$this->tokens->revoke($token);
		}
	}

	/**
	 * Disconnects an app: its grant, codes, refresh tokens, and access
	 * tokens.
	 *
	 * @throws OAuthError
	 * @throws AuthException
	 */
	public function disconnect(OAuthGrant $grant): void
	{
		$this->tokens->revokeClient($grant->account, $grant->client);
		$this->grants->delete($grant);
	}

	/**
	 * Exchanges a code for tokens.
	 *
	 * @param  array<mixed>         $params
	 * @return array<string, mixed>
	 * @throws OAuthError
	 * @throws AuthException
	 */
	private function exchange(array $params): array
	{
		$code     = is_string($params['code'] ?? null) ? $this->grants->secret($params['code'], OAuthSecret::CODE) : null;
		$verifier = $params['code_verifier'] ?? null;
		$grant    = $code === null ? null : $this->grants->byId($code->grant);
		$client   = $grant === null ? null : $this->clients->byId($grant->client);

		if ($code === null || $grant === null || $client === null || $code->used || $code->expires < $this->clock->now()->getTimestamp()) {
			throw OAuthError::grant('The code is wrong, used, or expired. Connect again.');
		}

		$this->grants->used($code);

		if (($params['client_id'] ?? null) !== $client->clientId || ($params['redirect_uri'] ?? null) !== $code->redirectUri) {
			throw OAuthError::grant('The code was given to another app, or for another address.');
		}

		if (! is_string($verifier) || preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) !== 1 || ! hash_equals($code->challenge, self::challenge($verifier))) {
			throw OAuthError::grant('The PKCE "code_verifier" doesn\'t match the challenge.');
		}

		$this->checkResource($params);

		return $this->tokensFor($grant, $client);
	}

	/**
	 * Replaces a refresh token with new tokens. One used before revokes
	 * the app, since it leaked.
	 *
	 * @param  array<mixed>         $params
	 * @return array<string, mixed>
	 * @throws OAuthError
	 * @throws AuthException
	 */
	private function refresh(array $params): array
	{
		$secret = is_string($params['refresh_token'] ?? null) ? $this->grants->secret($params['refresh_token'], OAuthSecret::REFRESH) : null;
		$grant  = $secret === null ? null : $this->grants->byId($secret->grant);
		$client = $grant === null ? null : $this->clients->byId($grant->client);

		if ($secret === null || $grant === null || $client === null || $secret->expires < $this->clock->now()->getTimestamp()) {
			throw OAuthError::grant('The refresh token is wrong, revoked, or expired. Connect again.');
		}

		if ($secret->used) {
			$this->disconnect($grant);

			throw OAuthError::grant('That refresh token was used before, so the app is disconnected. Connect again.');
		}

		if (($params['client_id'] ?? $client->clientId) !== $client->clientId) {
			throw OAuthError::grant('The refresh token was given to another app.');
		}

		$this->checkResource($params);
		$this->grants->used($secret);

		return $this->tokensFor($grant, $client);
	}

	/**
	 * Hands out an access token and a refresh token for a grant, when its
	 * account may still connect apps.
	 *
	 * @return array<string, mixed>
	 * @throws OAuthError
	 * @throws AuthException
	 */
	private function tokensFor(OAuthGrant $grant, OAuthClient $client): array
	{
		$account = $this->accounts->findById($grant->account);

		if ($account === null || ! $this->mayConnect($account)) {
			throw OAuthError::grant('The account that connected the app can\'t use it now.');
		}

		[, $access] = $this->tokens->issue($account, $client->id, $client->name, $grant->capabilities, self::ACCESS_LIFETIME);
		$refresh    = $this->grants->issue(OAuthSecret::REFRESH, $grant->id, self::REFRESH_LIFETIME);

		$this->grants->save($grant->withConnected($this->clock->now()->getTimestamp()));

		return [
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => self::ACCESS_LIFETIME,
			'refresh_token' => $refresh,
			'scope'         => $grant->scope()->value
		];
	}

	/**
	 * Checks a token request's `resource`, when it names one.
	 *
	 * @param  array<mixed> $params
	 * @throws OAuthError
	 */
	private function checkResource(array $params): void
	{
		$resource = $params['resource'] ?? null;

		if ($resource !== null && (! is_string($resource) || rtrim($resource, '/') !== $this->resource())) {
			throw new OAuthError('invalid_target', sprintf('Tokens here are for %s alone.', $this->resource()));
		}
	}

	/**
	 * Returns the widest scope a request names, `account` when it names
	 * none, or `null` when it names only scopes this server doesn't have.
	 */
	public static function scopeOf(mixed $scope): ?TokenScope
	{
		if (! is_string($scope) || trim($scope) === '') {
			return TokenScope::Account;
		}

		$asked = array_values(array_filter(array_map(TokenScope::tryFrom(...), preg_split('/\s+/', trim($scope)) ?: [])));

		return array_reduce($asked, static fn (?TokenScope $widest, TokenScope $scope): TokenScope => $widest === null || $scope->covers($widest) ? $scope : $widest);
	}

	/**
	 * Returns PKCE's S256 challenge for a verifier.
	 */
	public static function challenge(string $verifier): string
	{
		return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
	}
}
