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
 * The site's roles: the built-ins, with `AuthConfig::$roles` replacing or
 * adding by name.
 */
final readonly class Roles
{
	/**
	 * Roles by name.
	 *
	 * @var array<string, Role>
	 */
	private array $roles;

	public function __construct(AuthConfig $config)
	{
		$roles = [];

		foreach ([...array_map(static fn (BuiltInRole $role): Role => $role->role(), BuiltInRole::cases()), ...$config->roles] as $role) {
			$roles[$role->name] = $role;
		}

		$this->roles = $roles;
	}

	/**
	 * Returns a role, or `null` when there's none by that name.
	 */
	public function get(string $name): ?Role
	{
		return $this->roles[$name] ?? null;
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
