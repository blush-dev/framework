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
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;

/**
 * What an account may do to accounts and roles in the admin (D-312), with
 * the capabilities for each action (D-362), so managing them never grants
 * more than one has and never locks the site out:
 *
 * - **No more than you:** you may give a role, or make or change one,
 *   only when you have every capability it grants; and you may change an
 *   account only when it can't do anything you can't.
 * - **Not yourself:** your own roles, suspension, password link, and
 *   removal are changed by someone else (or `bin/blush`). Your password
 *   is on Your profile.
 * - **Someone stays in charge:** after any change, an account that isn't
 *   suspended still has every capability for managing accounts and
 *   roles (`Capability::users()`).
 * - **Owners (D-500):** only an owner changes an owner's account or gives
 *   the owner role, whatever another role has been given. Since nobody
 *   changes their own account, the last owner is never taken away here.
 *   While a site has no owner (one that isn't suspended), an account that
 *   can do all the built-in administrator can may name one, itself
 *   included (`mayClaim()`), so a site without a shell can get one.
 *
 * The `account:*` commands don't ask: whoever runs them has the site.
 */
final readonly class PeopleRules
{
	public function __construct(
		private Permissions $permissions,
		private Capabilities $capabilities,
		private Accounts $accounts
	) {}

	/**
	 * Whether an account may change another: not itself, not an owner
	 * unless it's one, and not one that can do more.
	 */
	public function manages(Account $actor, Account $account): bool
	{
		return $actor->username !== $account->username
			&& (! $account->isOwner() || $actor->isOwner())
			&& array_diff($this->permissions->capabilities($account), $this->permissions->capabilities($actor)) === [];
	}

	/**
	 * Whether an account may give a role: it has every registered
	 * capability the role grants, and the owner role only as an owner, or
	 * while the site has none (`mayClaim()`).
	 */
	public function mayGrant(Account $actor, Role $role): bool
	{
		if ($role->name === BuiltInRole::Owner->value) {
			return $actor->isOwner() || $this->mayClaim($actor);
		}

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
	 * Whether an account may name the site's first owner (itself or
	 * another): the site has none, and the account can do all the
	 * built-in administrator can.
	 *
	 * @throws AuthException When an account's record is damaged.
	 */
	public function mayClaim(Account $actor): bool
	{
		return ! $actor->suspended
			&& $this->mayGrantAll($actor, BuiltInRole::Administrator->capabilities())
			&& ! $this->accounts->hasOwner();
	}

	/**
	 * Whether, with these accounts and roles, one that isn't suspended
	 * can still do everything to accounts and roles.
	 *
	 * @param list<Account> $accounts
	 */
	public function keepsManager(array $accounts, Roles $roles): bool
	{
		return array_any($accounts, static fn (Account $account): bool => ! $account->suspended && array_all(
			Capability::users(),
			static fn (Capability $capability): bool => array_any(
				$account->roles,
				static fn (string $name): bool => $roles->get($name)?->allows($capability->value) ?? false
			)
		));
	}
}
