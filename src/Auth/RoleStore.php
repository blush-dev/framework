<?php

/**
 * Role store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Where the roles made or changed in the admin are kept (D-312): custom
 * roles, and the capabilities of a built-in role other than the
 * administrator. A file outside git by default (`FileRoleStore`), so a
 * pull never changes who can do what.
 */
interface RoleStore
{
	/**
	 * Returns the stored roles.
	 *
	 * @return list<Role>
	 * @throws AuthException When the store is damaged.
	 */
	public function all(): array;

	/**
	 * Replaces the stored roles.
	 *
	 * @param list<Role> $roles
	 * @throws AuthException When they can't be saved.
	 */
	public function save(array $roles): void;
}
