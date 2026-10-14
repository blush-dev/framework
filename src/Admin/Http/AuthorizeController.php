<?php

/**
 * Authorize controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Users\UsersJson;
use Blush\Auth\Account;
use Blush\Auth\Permissions;
use Blush\Auth\TokenScope;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Mcp\McpConfig;
use Blush\Mcp\OAuth\AuthorizationRequest;
use Blush\Mcp\OAuth\OAuthError;
use Blush\Mcp\OAuth\OAuthServer;

/**
 * The Connect an App screen's API (D-752), where a user approves an app
 * that asks to act as them on the MCP server:
 *
 * - `GET oauth/authorize?{the app's request}`: what the screen shows:
 *   the `site`, the `client` (its `name`, `host`, and whether it proved
 *   where it's from, `verified`), the signed-in `account`, whether it
 *   may connect apps (`mayConnect`, `api.connect`), the scope the app
 *   `asked` for, the `scopes` to choose from (each `available` when it's
 *   within what was asked), and the `capabilities` a custom choice may
 *   pick, as a role's screen draws them. An app approved before with as
 *   much answers `redirect` at once, with no screen. A request that
 *   can't be granted answers its `error`, with `redirect`, the app's
 *   address to tell it, only when the address is known to be the app's.
 * - `POST oauth/authorize`: `{"params", "decision", "scope",
 *   "capabilities"}` approves (`allow`) or refuses (`deny`), answering
 *   the `redirect` to send the user back to the app.
 */
final readonly class AuthorizeController
{
	public function __construct(
		private OAuthServer $oauth,
		private McpConfig $config,
		private Permissions $permissions,
		private UsersJson $json,
		private AppConfig $app
	) {}

	public function show(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->config->enabled) {
			return self::json(['error' => 'Apps can\'t connect to this site: its MCP server is off.', 'redirect' => null], Status::NotFound);
		}

		$account = self::account($request);

		try {
			$asked = $this->oauth->authorization($request->getQueryParams());
			$again = $this->oauth->approvedBefore($account, $asked);
		} catch (OAuthError $error) {
			return $this->refused($error, $request->getQueryParams());
		}

		$capabilities = $this->offered($account, $asked->scope);

		return self::json([
			'site'         => ['name' => $this->app->name, 'url' => $this->app->origin()],
			'client'       => ['name' => $asked->client->name, 'host' => $asked->client->host(), 'verified' => $asked->client->isVerified()],
			'account'      => ['username' => $account->username, 'name' => $account->name ?? $account->username],
			'mayConnect'   => $this->oauth->mayConnect($account),
			'asked'        => $asked->scope->value,
			'scopes'       => array_map(static fn (TokenScope $scope): array => [
				'key'       => $scope->value,
				'label'     => $scope->label(),
				'available' => $asked->scope->covers($scope)
			], TokenScope::cases()),
			'capabilities' => $this->json->capabilities($capabilities),
			'types'        => $this->json->types(),
			'redirect'     => $again
		]);
	}

	public function decide(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->config->enabled) {
			return self::json(['error' => 'Apps can\'t connect to this site: its MCP server is off.'], Status::NotFound);
		}

		$account = self::account($request);

		try {
			$input = json_decode((string) $request->getBody(), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$params = is_array($input) && is_array($input['params'] ?? null) ? $input['params'] : [];

		try {
			$asked = $this->oauth->authorization($params);
		} catch (OAuthError $error) {
			return $this->refused($error, $params);
		}

		if (! is_array($input) || ($input['decision'] ?? null) !== 'allow') {
			return self::json(['redirect' => $this->oauth->deny($asked)]);
		}

		try {
			return self::json(['redirect' => $this->oauth->approve($account, $asked, $this->chosen($input, $asked))]);
		} catch (OAuthError $error) {
			return $this->refused($error, $params);
		}
	}

	/**
	 * The capabilities chosen: a preset's, or those picked, or `null` for
	 * all the account's.
	 *
	 * @param  array<mixed>  $input
	 * @return ?list<string>
	 * @throws OAuthError
	 */
	private function chosen(array $input, AuthorizationRequest $asked): ?array
	{
		$scope = $input['scope'] ?? null;

		if ($scope === 'custom') {
			$picked = $input['capabilities'] ?? null;

			return is_array($picked) ? array_values(array_filter($picked, is_string(...))) : throw $asked->error('invalid_scope', 'Choose the capabilities to give it.');
		}

		$preset = is_string($scope) ? TokenScope::tryFrom($scope) : null;

		return $preset === null ? throw $asked->error('invalid_scope', 'Choose what it may do.') : $preset->capabilities();
	}

	/**
	 * The capabilities a custom choice may pick: the account's, within
	 * what the app asked for.
	 *
	 * @return list<string>
	 */
	private function offered(Account $account, TokenScope $asked): array
	{
		$own   = $this->permissions->capabilities($account);
		$limit = $asked->capabilities();

		return $limit === null ? $own : array_values(array_intersect($own, $limit));
	}

	/**
	 * Answers a request that can't be granted: its error, and, when the
	 * app's address is known to be its own, where to tell it.
	 *
	 * @param array<mixed> $params
	 */
	private function refused(OAuthError $error, array $params): ResponseInterface
	{
		$redirect = null;

		if ($error->redirectable && is_string($params['redirect_uri'] ?? null)) {
			$query    = ['error' => $error->error, 'error_description' => $error->getMessage(), 'iss' => $this->app->origin(), ...(is_string($params['state'] ?? null) ? ['state' => $params['state']] : [])];
			$redirect = $params['redirect_uri'] . (str_contains($params['redirect_uri'], '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}

		return self::json(['error' => $error->getMessage(), 'redirect' => $redirect], Status::BadRequest);
	}

	private static function account(ServerRequestInterface $request): Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : throw new LogicException('Connecting an app needs the Authenticate middleware.');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
