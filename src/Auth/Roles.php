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

use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * The site's roles, by name: the built-ins, then the roles the admin
 * keeps (`stored()`, D-312: custom roles, and the capabilities of a
 * built-in other than the owner and the member), then
 * `AuthConfig::$roles`, each replacing or adding by name (but never the
 * member, D-365). Each role's origin is kept too.
 * `RoleEditor` reloads them after a change, so everything sharing them
 * sees it.
 */
final class Roles
{
	/**
	 * The stored roles' table: on files, `storage/roles.json`, a one-file
	 * table keyed by role name (D-646), kept outside git by default, so a
	 * pull never changes who can do what.
	 */
	public const string TABLE = 'roles';

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
		private readonly RecordStores|RecordStore $stores,
		private readonly ClockInterface $clock
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

		foreach ($this->stored() as $role) {
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

	/**
	 * The stored roles' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, key: 'name', fields: ['name']);
	}

	/**
	 * Returns the stored roles: those made or changed in the admin.
	 *
	 * @return list<Role>
	 * @throws AuthException When they're damaged.
	 */
	public function stored(): array
	{
		try {
			$records = $this->records()->select(self::table(), new RecordQuery())->records;
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The roles can\'t be read: %s', $e->getMessage()), previous: $e);
		}

		return array_map(static function (Record $record): Role {
			$role = $record->fields;

			if (is_array($role['capabilities'] ?? null)) {
				$role['capabilities'] = array_values(array_filter($role['capabilities'], static fn (mixed $name): bool => ! in_array($name, Capability::RETIRED, true)));
			}

			try {
				return Role::fromArray($role);
			} catch (AuthException $e) {
				throw new AuthException(sprintf('The roles can\'t be read: %s', $e->getMessage()), previous: $e);
			}
		}, $records);
	}

	/**
	 * Replaces the stored roles, each keeping its record's id, and reads
	 * the site's roles again.
	 *
	 * @param  list<Role> $roles
	 * @throws AuthException When they can't be saved.
	 */
	public function save(array $roles): void
	{
		$table = self::table();

		try {
			$store = $this->records();

			$store->transaction(function () use ($store, $table, $roles): void {
				$kept = [];

				foreach ($store->select($table, new RecordQuery())->records as $record) {
					$name = $record->fields['name'] ?? null;

					if (is_string($name)) {
						$kept[$name] = $record;
					}
				}

				foreach ($roles as $role) {
					$record = $kept[$role->name] ?? Record::create($this->clock->now());

					$store->save($table, $record->withFields($role->toArray()));

					unset($kept[$role->name]);
				}

				foreach ($kept as $record) {
					$store->delete($table, $record->id);
				}
			});
		} catch (RecordException | StorageException $e) {
			throw new AuthException(sprintf('The roles couldn\'t be saved: %s', $e->getMessage()), previous: $e);
		}

		$this->reload();
	}

	/**
	 * The store that keeps the table.
	 *
	 * @throws StorageException
	 */
	private function records(): RecordStore
	{
		return $this->stores instanceof RecordStores ? $this->stores->store(self::table()) : $this->stores;
	}
}
