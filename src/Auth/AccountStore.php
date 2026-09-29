<?php

/**
 * Account store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Where accounts are kept. Files by default (`FileAccountStore`); a site
 * or extension can bind another.
 */
interface AccountStore
{
	/**
	 * Returns an account, or `null` when there's none by that username.
	 *
	 * @throws AuthException When the account's record is damaged.
	 */
	public function find(string $username): ?Account;

	/**
	 * Returns every account, sorted by username.
	 *
	 * @return list<Account>
	 * @throws AuthException When a record is damaged.
	 */
	public function all(): array;

	/**
	 * Whether there are any accounts.
	 */
	public function isEmpty(): bool;

	/**
	 * Saves an account, adding or replacing it.
	 */
	public function save(Account $account): void;

	/**
	 * Deletes an account.
	 */
	public function delete(string $username): void;
}
