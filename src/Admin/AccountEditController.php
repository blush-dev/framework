<?php

/**
 * Admin account editing controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Roles;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Creates, changes, and removes accounts from the admin (D-312), within
 * `PeopleRules`. Each needs `accounts.view` and its own capability
 * (D-362):
 *
 * - `POST accounts` (`accounts.create`; its first roles also need
 *   `accounts.roles`, else it's a member, D-365): `{"username",
 *   "roles", "author", "name"}` (the
 *   last two optional) makes an account
 *   with no password and a one-time link for choosing one (not named
 *   `new`, the admin's screen for making one). The answer
 *   (`201`) is the `account` and its `link`: the `url` to send and when
 *   it `expires`. The link's token is in the URL's fragment, so it never
 *   reaches a server log, and it's shown this once.
 * - `PATCH accounts/{username}`: any of `roles` (`accounts.roles`),
 *   `author` (`null` unlinks) and `name` (`null` or empty takes it
 *   away, D-322; both `accounts.edit`), and `suspended`
 *   (`accounts.suspend`); answers with the `account`.
 * - `POST accounts/{username}/link` (`accounts.edit`): a new password
 *   link, replacing any other; the account's password keeps working
 *   until it's used.
 * - `DELETE accounts/{username}` (`accounts.delete`): removes the
 *   account (`204`). Its author entry and the entries crediting it
 *   stay.
 *
 * Refusals are `403` (not allowed), `404` (no such account), or `422`
 * with an `error` and, when one input is at fault, its `field`.
 */
final readonly class AccountEditController
{
	/**
	 * What each capability lets an account do, for a refusal.
	 */
	private const array DOING = [
		'accounts.roles'   => 'give or take roles',
		'accounts.suspend' => 'suspend or reinstate accounts',
		'accounts.edit'    => 'change accounts\' names or profiles'
	];

	public function __construct(
		private Accounts $accounts,
		private AccountStore $store,
		private Roles $roles,
		private Permissions $permissions,
		private PeopleRules $rules,
		private PeopleJson $json,
		private AdminConfig $admin,
		private AppConfig $app
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		$actor = $this->manager($request, Capability::AccountsCreate);

		if ($actor === null) {
			return self::forbidden();
		}

		$input    = self::input($request);
		$username = is_string($input['username'] ?? null) ? strtolower(trim($input['username'])) : '';
		$roles    = self::strings($input['roles'] ?? null);
		$author   = is_string($input['author'] ?? null) && trim($input['author']) !== '' ? trim($input['author']) : null;
		$name     = is_string($input['name'] ?? null) ? Account::tidyName($input['name']) : null;

		if (! Account::isValidUsername($username)) {
			return self::error('Use lowercase letters, digits, ".", "_", and "-" for the username (up to 64), starting with a letter or digit.', field: 'username');
		}

		// The admin's New Account screen is at `accounts/new`.
		if ($username === 'new') {
			return self::error('"new" can\'t be a username here; pick another.', field: 'username');
		}

		if ($this->store->find($username) !== null) {
			return self::error(sprintf('There\'s already an account named "%s".', $username), field: 'username');
		}

		if ($name !== null && ! Account::isValidName($name)) {
			return self::error(sprintf('A name is up to %d characters on one line.', Account::NAME_LENGTH), field: 'name');
		}

		$roles = Accounts::settle($roles ?? []);

		// Without giving roles, a new account is a member (D-365).
		if ($roles !== [BuiltInRole::Member->value] && ! $this->permissions->can($actor, Capability::AccountsRoles)) {
			return self::error('You can\'t give roles, so a new account is a Member.', Status::Forbidden, 'roles');
		}

		$refusal = $this->checkRoles($actor, $roles);

		if ($refusal !== null) {
			return $refusal;
		}

		try {
			[$account, $token] = $this->accounts->invite($username, $roles, $author, $name);
		} catch (AuthException $e) {
			return self::error($e->getMessage(), field: 'author');
		}

		return self::json(['account' => $this->json->account($account, $actor), 'link' => $this->sendable($account, $token)], Status::Created);
	}

	public function update(ServerRequestInterface $request, string $username): ResponseInterface
	{
		$actor = $this->manager($request, Capability::AccountsView);

		if ($actor === null) {
			return self::forbidden();
		}

		$account = $this->target($actor, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		$input     = self::input($request);
		$roles     = array_key_exists('roles', $input) ? self::strings($input['roles']) : $account->roles;
		$suspended = $input['suspended'] ?? $account->suspended;
		$author    = array_key_exists('author', $input) ? $input['author'] : $account->author;
		$name      = array_key_exists('name', $input) ? $input['name'] : $account->name;

		if ($roles === null || ! is_bool($suspended) || ($author !== null && ! is_string($author)) || ($name !== null && ! is_string($name))) {
			return self::error('Send any of a list of "roles", an "author" (or null), a "name" (or null), and "suspended" (true or false).', Status::BadRequest);
		}

		$roles   = Accounts::settle($roles);
		$author  = is_string($author) && trim($author) !== '' ? trim($author) : null;
		$name    = is_string($name) ? Account::tidyName($name) : null;
		$needs   = array_filter([
			Capability::AccountsRoles->value   => $roles !== $account->roles,
			Capability::AccountsSuspend->value => $suspended !== $account->suspended,
			Capability::AccountsEdit->value    => $author !== $account->author || $name !== $account->name
		]);

		foreach (array_keys($needs) as $capability) {
			if (! $this->permissions->can($actor, $capability)) {
				return self::error(sprintf('You aren\'t allowed to %s.', self::DOING[$capability]), Status::Forbidden);
			}
		}

		if ($name !== null && ! Account::isValidName($name)) {
			return self::error(sprintf('A name is up to %d characters on one line.', Account::NAME_LENGTH), field: 'name');
		}

		$refusal = $roles === $account->roles ? null : $this->checkRoles($actor, $roles);

		if ($refusal !== null) {
			return $refusal;
		}

		$after = $account->withRoles($roles)->withSuspended($suspended);

		if (! $this->keepsManager($account, $after)) {
			return self::error(sprintf('That would leave no account that can manage accounts and roles, so %s keeps what it has.', $account->username));
		}

		try {
			$account = $roles === $account->roles ? $account : $this->accounts->setRoles($account, $roles);
			$account = $suspended === $account->suspended ? $account : $this->accounts->setSuspended($account, $suspended);
			$account = $author === $account->author ? $account : $this->accounts->setAuthor($account, $author);
			$account = $name === $account->name ? $account : $this->accounts->setName($account, $name);
		} catch (AuthException $e) {
			return self::error($e->getMessage(), field: 'author');
		}

		return self::json(['account' => $this->json->account($account, $actor)]);
	}

	public function link(ServerRequestInterface $request, string $username): ResponseInterface
	{
		$actor = $this->manager($request, Capability::AccountsEdit);

		if ($actor === null) {
			return self::forbidden();
		}

		$account = $this->target($actor, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		if ($account->suspended) {
			return self::error(sprintf('%s is suspended. Reinstate it first.', $account->username));
		}

		[$account, $token] = $this->accounts->issuePasswordLink($account);

		return self::json(['account' => $this->json->account($account, $actor), 'link' => $this->sendable($account, $token)]);
	}

	public function delete(ServerRequestInterface $request, string $username): ResponseInterface
	{
		$actor = $this->manager($request, Capability::AccountsDelete);

		if ($actor === null) {
			return self::forbidden();
		}

		$account = $this->target($actor, $username);

		if ($account instanceof ResponseInterface) {
			return $account;
		}

		if (! $this->keepsManager($account, null)) {
			return self::error(sprintf('%s is the only account left that can manage accounts and roles.', $account->username));
		}

		$this->store->delete($account->username);

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns the account to change, or the refusal.
	 */
	private function target(Account $actor, string $username): Account|ResponseInterface
	{
		$account = $this->store->find($username);

		return match (true) {
			$account === null                         => self::error(sprintf('There\'s no "%s" account.', $username), Status::NotFound),
			$account->username === $actor->username   => self::error('You can\'t change your own account here. Your password is on Your profile; another administrator can change the rest.', Status::Forbidden),
			! $this->rules->manages($actor, $account) => self::error(sprintf('%s can do things you can\'t, so you can\'t change it.', $account->username), Status::Forbidden),
			default                                   => $account
		};
	}

	/**
	 * Checks roles to give an account: at least one, each existing, and
	 * none granting what the actor can't do. Returns the refusal, or
	 * `null`.
	 *
	 * @param list<string> $roles
	 */
	private function checkRoles(Account $actor, array $roles): ?ResponseInterface
	{
		try {
			$this->accounts->checkRoles($roles);
		} catch (AuthException $e) {
			return self::error($e->getMessage(), field: 'roles');
		}

		foreach ($roles as $name) {
			$role = $this->roles->get($name);

			if ($role !== null && ! $this->rules->mayGrant($actor, $role)) {
				return self::error(sprintf('You can\'t give the %s role: it can do things you can\'t.', $role->label), Status::Forbidden, 'roles');
			}
		}

		return null;
	}

	/**
	 * Whether someone can still manage accounts and roles once an account is
	 * changed (or removed, for `null`).
	 *
	 * @throws AuthException When an account's record is damaged.
	 */
	private function keepsManager(Account $account, ?Account $after): bool
	{
		$accounts = array_values(array_filter($this->store->all(), static fn (Account $item): bool => $item->username !== $account->username));

		return $this->rules->keepsManager($after === null ? $accounts : [...$accounts, $after], $this->roles);
	}

	/**
	 * Describes a password link to send.
	 *
	 * @return array{url: string, expires: ?int}
	 */
	private function sendable(Account $account, string $token): array
	{
		$fragment = http_build_query(['account' => $account->username, 'token' => $token]);

		return [
			'url'     => $this->app->absoluteUrl("{$this->admin->path}/set-password#{$fragment}"),
			'expires' => $account->passwordLink?->expires
		];
	}

	/**
	 * Returns the signed-in account when it may see accounts and use a
	 * capability.
	 */
	private function manager(ServerRequestInterface $request, Capability $capability): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::AccountsView) && $this->permissions->can($account, $capability) ? $account : null;
	}

	/**
	 * Returns a list of strings, or `null` when it isn't one.
	 *
	 * @return ?list<string>
	 */
	private static function strings(mixed $value): ?array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			return null;
		}

		$strings = [];

		foreach ($value as $item) {
			if (! is_string($item)) {
				return null;
			}

			$strings[] = $item;
		}

		return array_values(array_unique($strings));
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

	private static function forbidden(): ResponseInterface
	{
		return self::error('You aren\'t allowed to do that to accounts.', Status::Forbidden);
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
