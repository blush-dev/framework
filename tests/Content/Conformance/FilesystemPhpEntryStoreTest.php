<?php

/**
 * Filesystem entry store test, without SQLite.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Conformance;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Blush\Content\Entries;
use Blush\Content\Index\IndexStore;
use Blush\Content\Index\SqliteIndex;
use Blush\Core\Application;
use Blush\Storage\Record\RecordStores;

/**
 * The filesystem driver with its SQLite index turned off: queries read
 * the PHP index's rows, as on a host without SQLite (D-659).
 */
#[CoversClass(IndexStore::class)]
#[CoversClass(SqliteIndex::class)]
final class FilesystemPhpEntryStoreTest extends EntryStoreConformance
{
	#[Override]
	protected function settings(): array
	{
		return ['sqliteIndex' => false];
	}

	#[Override]
	protected function stores(Application $app): RecordStores
	{
		return $app->container()->make(RecordStores::class);
	}

	#[Override]
	protected function entries(Application $app, RecordStores $stores): Entries
	{
		return $app->container()->make(Entries::class);
	}

	public function testNoSqliteIndexIsKept(): void
	{
		$sqlite = $this->app->container()->make(SqliteIndex::class);

		$this->assertFalse($sqlite->enabled());
		$this->assertFileDoesNotExist($sqlite->path());
	}
}
