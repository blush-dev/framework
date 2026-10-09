<?php

/**
 * Role editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Makes and changes the roles the admin keeps (D-312), `Roles::stored()`:
 *
 * - **Custom roles** are created, changed (label, description, and
 *   capabilities), and deleted, but not while an account holds one.
 * - **Built-in roles** other than the owner and the member have their
 *   capabilities changed, and deleting the change resets them (D-500:
 *   the administrator's `*` comes back).
 * - Roles from `config/auth.php`, the owner, and the member aren't
 *   changed.
 *
 * Capabilities must be registered (`*` is only ever kept, on a role
 * that has it), except ones a role already has, which stay when an extension that
 * registered them is off. Each change reloads the site's `Roles` and
 * returns them.
 */
final readonly class RoleEditor
{
	public function __construct(
		private Roles $roles,
		private Capabilities $capabilities,
		private Accounts $accounts
	) {}

	/**
	 * Returns the stored roles, to put back with `restore()`.
	 *
	 * @return list<Role>
	 * @throws AuthException When the store is damaged.
	 */
	public function stored(): array
	{
		return $this->roles->stored();
	}

	/**
	 * Puts stored roles back as they were.
	 *
	 * @param  list<Role> $roles
	 * @throws AuthException
	 */
	public function restore(array $roles): Roles
	{
		return $this->save($roles);
	}

	/**
	 * Creates a custom role.
	 *
	 * @param  list<string> $capabilities
	 * @throws AuthException For a name in use or invalid, an empty label,
	 *                       or a capability that isn't registered.
	 */
	public function create(string $name, string $label, string $description, array $capabilities): Roles
	{
		if ($this->roles->has($name)) {
			throw new AuthException(sprintf('There\'s already a role named "%s".', $name));
		}

		$role = new Role($name, self::label($label), $this->checked($capabilities, []), trim($description));

		return $this->save([...$this->roles->stored(), $role]);
	}

	/**
	 * Changes a role: a custom role's label, description, or
	 * capabilities, or a built-in's capabilities (`null` leaves one as it
	 * is).
	 *
	 * @param  ?list<string> $capabilities
	 * @throws AuthException When the role doesn't exist or can't be
	 *                       changed, or for an empty label or a
	 *                       capability that isn't registered.
	 */
	public function update(string $name, ?string $label = null, ?string $description = null, ?array $capabilities = null): Roles
	{
		$current = $this->editable($name);
		$custom  = BuiltInRole::tryFrom($name) === null;

		if (! $custom && (($label !== null && $label !== $current->label) || ($description !== null && $description !== $current->description))) {
			throw new AuthException(sprintf('The %s role\'s name and description are built in; only its capabilities can change.', $current->label));
		}

		$role = new Role(
			$name,
			$label === null ? $current->label : self::label($label),
			$capabilities === null ? $current->capabilities : $this->checked($capabilities, $current->capabilities),
			$description === null ? $current->description : trim($description)
		);

		$stored = array_values(array_filter($this->roles->stored(), static fn (Role $item): bool => $item->name !== $name));

		return $this->save([...$stored, $custom ? $role : new Role($name, $role->label, $role->capabilities)]);
	}

	/**
	 * Deletes a custom role, or resets a changed built-in.
	 *
	 * @throws AuthException When the role doesn't exist, can't be changed,
	 *                       is a built-in as it was, or is a custom role
	 *                       an account holds.
	 */
	public function delete(string $name): Roles
	{
		$role = $this->editable($name);

		if ($this->roles->origin($name) === RoleOrigin::BuiltIn) {
			throw new AuthException(sprintf('The %s role is built in and hasn\'t changed, so there\'s nothing to reset.', $role->label));
		}

		if (BuiltInRole::tryFrom($name) === null) {
			$holders = array_map(
				static fn (Account $account): string => $account->username,
				array_values(array_filter($this->accounts->all(), static fn (Account $account): bool => in_array($name, $account->roles, true)))
			);

			if ($holders !== []) {
				throw new AuthException(sprintf('Accounts still hold the %s role (%s). Take it from them first.', $role->label, implode(', ', $holders)));
			}
		}

		return $this->save(array_values(array_filter($this->roles->stored(), static fn (Role $item): bool => $item->name !== $name)));
	}

	/**
	 * Saves the stored roles and reloads the site's.
	 *
	 * @param  list<Role> $roles
	 * @throws AuthException
	 */
	private function save(array $roles): Roles
	{
		$this->roles->save($roles);

		return $this->roles;
	}

	/**
	 * Returns a role the admin may change.
	 *
	 * @throws AuthException
	 */
	private function editable(string $name): Role
	{
		$role   = $this->roles->get($name) ?? throw new AuthException(sprintf('There\'s no "%s" role.', $name));
		$origin = $this->roles->origin($name);

		return $origin?->editable($name) === true
			? $role
			: throw new AuthException($origin === RoleOrigin::Config
				? sprintf('The %s role is defined in config/auth.php, so it\'s changed there.', $role->label)
				: ($name === BuiltInRole::Member->value
					? sprintf('The %s role never has a capability.', $role->label)
					: sprintf('The %s role always has every capability, so it\'s never changed.', $role->label)));
	}

	/**
	 * Returns capabilities checked: each registered, or already the
	 * role's.
	 *
	 * @param  list<string> $capabilities
	 * @param  list<string> $kept
	 * @return list<string>
	 * @throws AuthException
	 */
	private function checked(array $capabilities, array $kept): array
	{
		foreach ($capabilities as $capability) {
			if (! $this->capabilities->has($capability) && ! in_array($capability, $kept, true)) {
				throw new AuthException(sprintf('There\'s no "%s" capability.', $capability));
			}
		}

		return array_values(array_unique($capabilities));
	}

	/**
	 * Returns a label, trimmed.
	 *
	 * @throws AuthException When it's empty.
	 */
	private static function label(string $label): string
	{
		$label = trim($label);

		return $label === '' ? throw new AuthException('A role needs a name.') : $label;
	}
}
