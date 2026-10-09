<?php

/**
 * Storage service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Override;
use Blush\Auth\RecordAccountStore;
use Blush\Content\Record\EntryTable;
use Blush\Data\RecordDataStore;
use Blush\Job\RecordJobStore;
use Blush\Session\RecordSessionStore;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\TableRegistry;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteRecordStore;

/**
 * Binds the storage drivers (D-486, D-642), and the record layer's
 * services (D-643): the stores by area, the table registry, and the
 * filesystem driver's layouts and transactions, and the SQLite driver's
 * store, one per site, on the database `StorageConfig` names (D-662).
 * Each subsystem binds its own contracts through `ServiceProvider`'s
 * `STORAGE`, which these resolve.
 */
final class StorageServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		StorageDriverFactory::class,
		StorageResolver::class,
		RecordStores::class,
		TableRegistry::class,
		FileLayouts::class,
		FileTransactions::class
	];

	/**
	 * Binds the driver registry, seeded with the built-in drivers.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			StorageDriverRegistry::class,
			static function (): StorageDriverRegistry {
				$registry = new StorageDriverRegistry();
				new StorageDriverRegistrar($registry)->register();

				return $registry;
			}
		);

		// The core tables a database driver keeps (D-649, D-665), for
		// `storage:sync` and `storage:copy`; subsystems and plugins add
		// their own.
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				foreach ([EntryTable::table(), Ref::table(StorageArea::Content), RecordDataStore::table(), RecordAccountStore::table(), RecordSessionStore::table(), RecordJobStore::table(), RecordJobStore::stateTable()] as $table) {
					$tables->register($table);
				}
			}
		});

		$this->container->singleton(
			SqliteRecordStore::class,
			static fn (ServiceResolver $resolver): SqliteRecordStore => SqliteRecordStore::forSite($resolver->make(StorageConfig::class), $resolver->make(Paths::class)->root)
		);
	}

	/**
	 * Fails at boot, plainly, when an area uses the SQLite driver and PHP
	 * can't open SQLite databases with SQLite's JSON functions (D-640).
	 *
	 * @throws StorageException
	 */
	#[Override]
	public function boot(): void
	{
		if ($this->container->make(StorageConfig::class)->uses(StorageConfig::SQLITE) && ! SqliteConnection::available()) {
			throw new StorageException(SqliteStorage::UNAVAILABLE);
		}
	}
}
