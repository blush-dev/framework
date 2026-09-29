<?php

/**
 * Admin people controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers the admin's read-only people screens (D-249), for accounts
 * with `accounts.manage`:
 *
 * - `GET roles`: every capability (`name`, `label`) and every role, with
 *   its `name`, `label`, `capabilities` (`*` for all), whether it's
 *   `builtIn`, and the `accounts` that hold it.
 * - `GET accounts`: every account's `username`, `roles`, `author`,
 *   `created`, and `lastLogin` (Unix times), sorted by username. Password hashes
 *   and preferences stay on the server.
 *
 * Roles and capabilities are set in `config/auth.php` and accounts with
 * `account:*` commands, so these only show them.
 */
final readonly class PeopleController
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private AccountStore $accounts,
		private Permissions $permissions
	) {}

	public function roles(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$accounts = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		$roles = [];

		foreach ($this->roles->all() as $role) {
			$roles[] = [
				'name'         => $role->name,
				'label'        => $role->label,
				'capabilities' => $role->capabilities,
				'builtIn'      => BuiltInRole::tryFrom($role->name) !== null,
				'accounts'     => array_values(array_map(
					static fn (Account $account): string => $account->username,
					array_filter($accounts, static fn (Account $account): bool => in_array($role->name, $account->roles, true))
				))
			];
		}

		$capabilities = [];

		foreach ($this->capabilities->all() as $name => $label) {
			$capabilities[] = ['name' => $name, 'label' => $label];
		}

		return Response::json(['capabilities' => $capabilities, 'roles' => $roles, 'all' => Role::ALL], headers: ['Cache-Control' => 'no-store']);
	}

	public function accounts(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$all = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		$accounts = array_map(static fn (Account $account): array => [
			'username'  => $account->username,
			'roles'     => $account->roles,
			'author'    => $account->author,
			'created'   => $account->created,
			'lastLogin' => $account->lastLogin
		], $all);

		return Response::json(['accounts' => $accounts], headers: ['Cache-Control' => 'no-store']);
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::AccountsManage);
	}

	private static function damaged(AuthException $error): ResponseInterface
	{
		return Response::json(['error' => $error->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
	}

	private static function forbidden(): ResponseInterface
	{
		return Response::json(['error' => 'You aren\'t allowed to manage accounts.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
	}
}
