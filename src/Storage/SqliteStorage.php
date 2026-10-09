<?php

/**
 * SQLite storage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Override;
use Blush\Auth\AccountStore;
use Blush\Auth\RecordAccountStore;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\RecordLocations;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\RecordContentWriter;
use Blush\Data\DataStore;
use Blush\Data\RecordDataStore;
use Blush\Job\JobStore;
use Blush\Job\RecordJobStore;
use Blush\Session\RecordSessionStore;
use Blush\Session\SessionStore;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Sql\SqliteRecordStore;

/**
 * The SQLite driver (D-640, D-662): a site's records in one database
 * file, `user/site.sqlite` unless `StorageConfig::$sqlite` names another.
 * Every area shares one `SqliteRecordStore`: content, worked out and
 * written as records (`RecordLocations`, `RecordContentWriter`); data,
 * accounts, and roles as keyed tables (`RecordDataStore`,
 * `RecordAccountStore`, `RecordRoleStore`); and sessions and jobs built
 * on records inside (D-645).
 */
final readonly class SqliteStorage implements Storage
{
	/**
	 * What a site is told when PHP can't open SQLite databases.
	 */
	public const string UNAVAILABLE = 'The "sqlite" storage driver needs PHP\'s pdo_sqlite extension, with SQLite\'s JSON functions. Turn the extension on, or set the storage driver back to "filesystem".';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function bindings(): array
	{
		return [
			ContentWriter::class  => RecordContentWriter::class,
			EntryLocations::class => RecordLocations::class,
			DataStore::class      => RecordDataStore::class,
			RecordStore::class    => SqliteRecordStore::class,
			AccountStore::class   => RecordAccountStore::class,
			SessionStore::class   => RecordSessionStore::class,
			JobStore::class       => RecordJobStore::class
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function commands(StorageArea $area): array
	{
		return [];
	}
}
