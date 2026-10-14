<?php

/**
 * Tokens controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Users\UserRules;
use Blush\Admin\Users\UsersJson;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Token;
use Blush\Auth\Tokens;
use Blush\Auth\TokenScope;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthGrants;
use Blush\Mcp\OAuth\OAuthServer;

/**
 * An account's API tokens in the admin (D-749), on its screen:
 *
 * - `GET accounts/{username}/tokens`: its `tokens`, newest first, as
 *   `token()` describes each; whether the viewer may `make` one; the
 *   `capabilities` a new one may be narrowed to, as a role's screen
 *   draws them (`UsersJson::capabilities()`, with the `types`): the
 *   account's, and, for someone else's, only those the viewer has too.
 * - `POST accounts/{username}/tokens`: `{"name", "expires",
 *   "capabilities"}` makes one; `expires` is a number of days from
 *   `Tokens::LIFETIMES` (90 when left out) or `null` for never, and
 *   `capabilities` a list of names or `null` for all the account's.
 *   The answer (`201`) is the `token` and its `secret`, the token's
 *   text, shown this once.
 * - `DELETE accounts/{username}/tokens/{id}`: revokes one (`204`).
 * - `DELETE accounts/{username}/tokens`: revokes all of them, and
 *   disconnects its apps, and answers with how many (`revoked`).
 * - `DELETE accounts/{username}/apps/{id}`: disconnects an app (D-752):
 *   its approval and every token it holds (`204`). The list answers
 *   `apps`, one row an app, and leaves apps' own tokens out of
 *   `tokens`.
 *
 * Who may do what:
 *
 * - **Your own:** seen and revoked by you always; made with
 *   `api.tokens`.
 * - **Someone else's:** seen and revoked with `accounts.view` and
 *   `accounts.edit`, an owner's only by an owner (D-500). Made only for
 *   a token-only account (D-744) you may change (`UserRules`), and
 *   never with a capability you don't have, so nobody acts as someone
 *   who can do more through a token. A user's own sign-in account
 *   makes its own.
 *
 * Refusals are `403`, `404` (no such account or token), or `422` with an
 * `error` and, when one input is at fault, its `field`.
 */
final readonly class TokensController
{
	public function __construct(
		private Accounts $accounts,
		private AccountProfiles $profiles,
		private Tokens $tokens,
		private Permissions $permissions,
		private UsersJson $json,
		private UserRules $rules,
		private ClockInterface $clock,
		private OAuthGrants $grants,
		private OAuthClients $clients,
		private OAuthServer $oauth
	) {}

	public function index(ServerRequestInterface $request, string $username): ResponseInterface
	{
		[$actor, $account] = $this->target($request, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		$offered = $this->offered($actor, $account);

		return self::json([
			'tokens'       => array_values(array_map($this->token(...), array_filter($this->tokens->forAccount($account), static fn (Token $token): bool => $token->client === null))),
			'apps'         => $this->apps($account),
			'make'         => $this->mayMake($actor, $account),
			'capabilities' => $this->json->capabilities($offered),
			'types'        => $this->json->types()
		]);
	}

	public function create(ServerRequestInterface $request, string $username): ResponseInterface
	{
		[$actor, $account] = $this->target($request, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		if (! $this->mayMake($actor, $account)) {
			return self::error($actor->username === $account->username
				? 'You aren\'t allowed to make API tokens.'
				: sprintf('%s signs in, so they make their own tokens. Tokens are made for others only for token-only accounts you may change.', $this->profiles->displayName($account)), Status::Forbidden);
		}

		$input   = self::input($request);
		$problem = $this->problem($actor, $account, $input);

		if ($problem !== null) {
			return self::error($problem[0], field: $problem[1]);
		}

		[$token, $secret] = $this->make($actor, $account, $input);

		return self::json(['token' => $this->token($token), 'secret' => $secret], Status::Created);
	}

	public function delete(ServerRequestInterface $request, string $username, string $id): ResponseInterface
	{
		[, $account] = $this->target($request, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		$token = $this->tokens->find($id);

		if ($token === null || $token->account !== $account->id || $token->client !== null) {
			return self::error('There\'s no such token.', Status::NotFound);
		}

		$this->tokens->revoke($token);

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	public function deleteAll(ServerRequestInterface $request, string $username): ResponseInterface
	{
		[, $account] = $this->target($request, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		$apps = $this->grants->forAccount($account->id);

		foreach ($apps as $grant) {
			$this->oauth->disconnect($grant);
		}

		return self::json(['revoked' => $this->tokens->revokeAll($account) + count($apps)]);
	}

	/**
	 * Disconnects an app (D-752): its approval and every token it holds.
	 */
	public function disconnect(ServerRequestInterface $request, string $username, string $id): ResponseInterface
	{
		[, $account] = $this->target($request, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		$grant = $this->grants->byId($id);

		if ($grant === null || $grant->account !== $account->id) {
			return self::error('There\'s no such connected app.', Status::NotFound);
		}

		$this->oauth->disconnect($grant);

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes an account's connected apps (D-752), one row an app: its
	 * grant's `id`, the app's `name` and `host`, the `scope` it was given
	 * (`read`, `drafts`, `account`, or `custom`), its `capabilities`, when
	 * it was `approved`, and when it last `connected`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function apps(Account $account): array
	{
		$apps = [];

		foreach ($this->grants->forAccount($account->id) as $grant) {
			$client = $this->clients->byId($grant->client);
			$scope  = $grant->scope();

			$apps[] = [
				'id'           => $grant->id,
				'name'         => $client->name ?? 'An app',
				'host'         => $client?->host(),
				'scope'        => $scope === TokenScope::Account && $grant->capabilities !== null ? 'custom' : $scope->value,
				'capabilities' => $grant->capabilities,
				'approved'     => $grant->approved,
				'connected'    => $grant->connected
			];
		}

		return $apps;
	}

	/**
	 * Returns what's wrong with the admin's input for a new token, as a
	 * message and the input at fault, or `null`: a name it needs, a
	 * lifetime from `Tokens::LIFETIMES` (or `null`), and capabilities as
	 * a list of names (or `null`), none of them one the actor doesn't
	 * have when the token isn't its own.
	 *
	 * @param  array<mixed> $input
	 * @return ?array{string, string}
	 */
	public function problem(Account $actor, Account $account, array $input): ?array
	{
		$name         = is_string($input['name'] ?? null) ? (Account::tidyName($input['name']) ?? '') : '';
		$days         = array_key_exists('expires', $input) ? $input['expires'] : Tokens::DEFAULT_LIFETIME;
		$capabilities = $input['capabilities'] ?? null;

		if ($name === '' || ! Token::isValidName($name)) {
			return [sprintf('Give the token a name that says what it\'s for, up to %d characters.', Token::NAME_LENGTH), 'name'];
		}

		if ($days !== null && ! in_array($days, Tokens::LIFETIMES, true)) {
			return [sprintf('A token lasts %s days, or never.', implode(', ', Tokens::LIFETIMES)), 'expires'];
		}

		if ($capabilities !== null && (! is_array($capabilities) || ! array_is_list($capabilities) || ! array_all($capabilities, static fn (mixed $item): bool => is_string($item) && preg_match(Capabilities::NAME, $item) === 1))) {
			return ['A token\'s capabilities are a list of capability names, or null for all of its account\'s.', 'capabilities'];
		}

		if ($actor->username === $account->username) {
			return null;
		}

		/** @var ?list<string> $capabilities */
		$asked = $capabilities ?? $this->permissions->capabilities($account);

		foreach ($asked as $capability) {
			if (! $this->permissions->can($actor, $capability)) {
				return [sprintf('You can\'t give a token "%s": it\'s a capability you don\'t have.', $capability), 'capabilities'];
			}
		}

		return null;
	}

	/**
	 * Makes a token from the admin's input, once `problem()` finds
	 * nothing wrong with it, and returns it with its text.
	 *
	 * @param  array<mixed> $input
	 * @return array{Token, string}
	 * @throws AuthException When it can't be saved.
	 */
	public function make(Account $actor, Account $account, array $input): array
	{
		$days         = array_key_exists('expires', $input) && (is_int($input['expires']) || $input['expires'] === null) ? $input['expires'] : Tokens::DEFAULT_LIFETIME;
		$capabilities = is_array($input['capabilities'] ?? null) ? array_values(array_filter($input['capabilities'], is_string(...))) : null;

		return $this->tokens->make($account, is_string($input['name'] ?? null) ? $input['name'] : '', $capabilities, $days, $actor->id);
	}

	/**
	 * Describes a token: its `id`, `name`, `capabilities` (`null` for
	 * all its account's), `created`, `createdBy` (`username` and
	 * `displayName`, or `null` for the command line or an account that's
	 * gone), `expires`, whether it has `expired`, and `lastUsed`. Never
	 * its hash.
	 *
	 * @return array<string, mixed>
	 */
	public function token(Token $token): array
	{
		$maker = $token->createdBy === '' ? null : $this->accounts->findById($token->createdBy);

		return [
			'id'           => $token->id,
			'name'         => $token->name,
			'capabilities' => $token->capabilities,
			'created'      => $token->created,
			'createdBy'    => $maker === null ? null : ['username' => $maker->username, 'displayName' => $this->profiles->displayName($maker)],
			'expires'      => $token->expires,
			'expired'      => $token->isExpired($this->clock->now()->getTimestamp()),
			'lastUsed'     => $token->lastUsed
		];
	}

	/**
	 * Whether an actor may make a token for an account: its own with
	 * `api.tokens`, or a token-only account it may change, with
	 * `accounts.edit`.
	 */
	public function mayMake(Account $actor, Account $account): bool
	{
		return $actor->username === $account->username
			? $this->permissions->can($actor, Capability::ApiTokens)
			: $account->tokenOnly && $this->permissions->can($actor, Capability::AccountsEdit) && $this->rules->manages($actor, $account);
	}

	/**
	 * Returns the capabilities a new token for an account may be narrowed
	 * to: its own, and, for someone else's, only those the actor has too.
	 *
	 * @return list<string>
	 */
	private function offered(Account $actor, Account $account): array
	{
		$own = $this->permissions->capabilities($account);

		return $actor->username === $account->username
			? $own
			: array_values(array_intersect($own, $this->permissions->capabilities($actor)));
	}

	/**
	 * Returns the signed-in account and the account whose tokens are
	 * asked for, or the refusal in the account's place: your own, or one
	 * whose tokens you may manage.
	 *
	 * @return array{Account, Account|ResponseInterface}
	 */
	private function target(ServerRequestInterface $request, string $username): array
	{
		$actor = $request->getAttribute(Account::class);

		if (! $actor instanceof Account) {
			return [new Account('nobody', ''), self::error('Sign in first.', Status::Unauthorized)];
		}

		if ($actor->username === $username) {
			return [$actor, $actor];
		}

		if (! $this->permissions->can($actor, Capability::AccountsView) || ! $this->permissions->can($actor, Capability::AccountsEdit)) {
			return [$actor, self::error('You aren\'t allowed to see other accounts\' tokens.', Status::Forbidden)];
		}

		$account = $this->accounts->find($username);

		return match (true) {
			$account === null                          => [$actor, self::error(sprintf('There\'s no "%s" account.', $username), Status::NotFound)],
			$account->isOwner() && ! $actor->isOwner() => [$actor, self::error(sprintf('%s is an owner, and only an owner can see or change an owner\'s tokens.', $account->username), Status::Forbidden)],
			default                                    => [$actor, $account]
		};
	}

	/**
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private static function error(string $message, Status $status = Status::UnprocessableContent, ?string $field = null): ResponseInterface
	{
		return self::json(['error' => $message, ...($field === null ? [] : ['field' => $field])], $status);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
