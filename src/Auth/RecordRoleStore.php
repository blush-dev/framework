<?php

/**
 * Record role store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;

/**
 * Keeps the admin's roles (D-312) as records in the `roles` table
 * (D-646), keyed by name, each as `Role::toArray()` writes it, with its
 * id. On files, the table is `storage/roles.json`, beside the accounts
 * and, like them, outside git and `user/`: `{"roles": [...]}`, as before
 * tables, each role gaining its `id` when the roles are next saved.
 * Retired capabilities (`Capability::RETIRED`) are dropped as they're
 * read.
 */
final readonly class RecordRoleStore implements RoleStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'roles';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The roles' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Accounts, key: 'name', fields: ['name']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		try {
			$records = $this->stores->query(self::table())->get()->records;
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
	 * @inheritDoc
	 */
	#[Override]
	public function save(array $roles): void
	{
		$table = self::table();

		try {
			$store = $this->stores->store($table);

			$store->transaction(function () use ($store, $table, $roles): void {
				$kept = [];

				foreach ($this->stores->query($table)->get()->records as $record) {
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
	}
}
