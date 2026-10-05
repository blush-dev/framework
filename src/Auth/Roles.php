<?php

/**
 * Roles.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * The site's roles, by name: the built-ins, then the roles the admin
 * keeps (`RoleStore`, D-312: custom roles, and the capabilities of a
 * built-in other than the administrator and the member), then
 * `AuthConfig::$roles`, each replacing or adding by name (but never the
 * member, D-365). Each role's origin is kept too.
 * `RoleEditor` reloads them after a change, so everything sharing them
 * sees it.
 */
final class Roles
{
	/**
	 * Roles by name.
	 *
	 * @var array<string, Role>
	 */
	private array $roles = [];

	/**
	 * Where each role comes from, by name.
	 *
	 * @var array<string, RoleOrigin>
	 */
	private array $origins = [];

	/**
	 * @throws AuthException When the stored roles are damaged.
	 */
	public function __construct(
		private readonly AuthConfig $config,
		private readonly RoleStore $store
	) {
		$this->reload();
	}

	/**
	 * Reads the roles again.
	 *
	 * @throws AuthException When the stored roles are damaged.
	 */
	public function reload(): void
	{
		$roles   = [];
		$origins = [];

		foreach (BuiltInRole::cases() as $builtIn) {
			$roles[$builtIn->value]   = $builtIn->role();
			$origins[$builtIn->value] = RoleOrigin::BuiltIn;
		}

		foreach ($this->store->all() as $role) {
			$builtIn = BuiltInRole::tryFrom($role->name);

			if ($builtIn?->isFixed() === true) {
				continue;
			}

			// A built-in keeps its name and description; only what it
			// can do changes.
			$roles[$role->name]   = $builtIn?->role()->withCapabilities($role->capabilities) ?? $role;
			$origins[$role->name] = $builtIn === null ? RoleOrigin::Custom : RoleOrigin::Changed;
		}

		foreach ($this->config->roles as $role) {
			// The owner always has every capability and the member none
			// (D-500, D-365), even from config.
			if (BuiltInRole::tryFrom($role->name)?->isFixed() === true) {
				continue;
			}

			$roles[$role->name]   = $role;
			$origins[$role->name] = RoleOrigin::Config;
		}

		$this->roles   = $roles;
		$this->origins = $origins;
	}

	/**
	 * Returns a role, or `null` when there's none by that name.
	 */
	public function get(string $name): ?Role
	{
		return $this->roles[$name] ?? null;
	}

	/**
	 * Returns where a role comes from, or `null` when there's none by
	 * that name.
	 */
	public function origin(string $name): ?RoleOrigin
	{
		return $this->origins[$name] ?? null;
	}
	/**
	 * Whether a role exists.
	 */
	public function has(string $name): bool
	{
		return isset($this->roles[$name]);
	}

	/**
	 * Returns every role, by name.
	 *
	 * @return array<string, Role>
	 */
	public function all(): array
	{
		return $this->roles;
	}
}
