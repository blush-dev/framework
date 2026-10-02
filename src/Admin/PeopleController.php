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
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers the admin's people screens (D-249), for accounts with
 * `accounts.manage`:
 *
 * - `GET roles`: every capability (`name`, `label`) and every role, as
 *   `PeopleJson::role()` describes it.
 * - `GET accounts`: every account, as `PeopleJson::account()` describes
 *   it, sorted by username.
 *
 * Changes go through `AccountEditController` and `RoleEditController`
 * (D-312).
 */
final readonly class PeopleController
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private AccountStore $accounts,
		private Permissions $permissions,
		private PeopleJson $json
	) {}

	public function roles(ServerRequestInterface $request): ResponseInterface
	{
		$viewer = $this->viewer($request);

		if ($viewer === null) {
			return self::forbidden();
		}

		try {
			$accounts = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		$capabilities = [];

		foreach ($this->capabilities->all() as $name => $label) {
			$capabilities[] = ['name' => $name, 'label' => $label];
		}

		return Response::json([
			'capabilities' => $capabilities,
			'roles'        => array_values(array_map(fn (Role $role): array => $this->json->role($role, $this->roles, $accounts, $viewer), $this->roles->all())),
			'all'          => Role::ALL
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function accounts(ServerRequestInterface $request): ResponseInterface
	{
		$viewer = $this->viewer($request);

		if ($viewer === null) {
			return self::forbidden();
		}

		try {
			$all = $this->accounts->all();
		} catch (AuthException $error) {
			return self::damaged($error);
		}

		return Response::json(['accounts' => array_map(fn (Account $account): array => $this->json->account($account, $viewer), $all)], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns the signed-in account when it may manage accounts.
	 */
	private function viewer(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::AccountsManage) ? $account : null;
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
