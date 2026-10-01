<?php

/**
 * People rules.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Auth\Account;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;

/**
 * What an account with `accounts.manage` may do to accounts and roles in
 * the admin (D-312), so managing them never grants more than one has and
 * never locks the site out:
 *
 * - **No more than you:** you may give a role, or make or change one,
 *   only when you have every capability it grants; and you may change an
 *   account only when it can't do anything you can't.
 * - **Not yourself:** your own roles, suspension, password link, and
 *   removal are changed by someone else (or `bin/blush`). Your password
 *   is on Your profile.
 * - **Someone stays in charge:** after any change, an account that isn't
 *   suspended still has `accounts.manage`.
 *
 * The `account:*` commands don't ask: whoever runs them has the site.
 */
final readonly class PeopleRules
{
	public function __construct(
		private Permissions $permissions,
		private Capabilities $capabilities
	) {}

	/**
	 * Whether an account may change another: not itself, and not one that
	 * can do more.
	 */
	public function manages(Account $actor, Account $account): bool
	{
		return $actor->username !== $account->username
			&& array_diff($this->permissions->capabilities($account), $this->permissions->capabilities($actor)) === [];
	}

	/**
	 * Whether an account has every registered capability a role grants.
	 */
	public function mayGrant(Account $actor, Role $role): bool
	{
		return $this->mayGrantAll($actor, array_values(array_filter(
			array_keys($this->capabilities->all()),
			static fn (string $capability): bool => $role->allows($capability)
		)));
	}

	/**
	 * Whether an account has every one of some capabilities that's
	 * registered (ones that aren't grant nothing).
	 *
	 * @param list<string> $capabilities
	 */
	public function mayGrantAll(Account $actor, array $capabilities): bool
	{
		$has = $this->permissions->capabilities($actor);

		return array_all(
			$capabilities,
			fn (string $capability): bool => ! $this->capabilities->has($capability) || in_array($capability, $has, true)
		);
	}

	/**
	 * Whether, with these accounts and roles, one that isn't suspended
	 * can still manage accounts.
	 *
	 * @param list<Account> $accounts
	 */
	public function keepsManager(array $accounts, Roles $roles): bool
	{
		return array_any($accounts, static fn (Account $account): bool => ! $account->suspended && array_any(
			$account->roles,
			static fn (string $name): bool => $roles->get($name)?->allows(Capability::AccountsManage->value) ?? false
		));
	}
}
