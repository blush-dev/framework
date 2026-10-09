<?php

/**
 * SQLite record store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage\Conformance;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Sql\SqlCompiler;
use Blush\Storage\Sql\SqlFragment;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteDialect;
use Blush\Storage\Sql\SqliteRecordStore;

#[CoversClass(SqliteRecordStore::class)]
#[CoversClass(SqliteConnection::class)]
#[CoversClass(SqliteDialect::class)]
#[CoversClass(SqlCompiler::class)]
#[CoversClass(SqlFragment::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class SqliteRecordStoreTest extends RecordStoreConformance
{
	#[Override]
	protected function store(): RecordStore
	{
		return SqliteRecordStore::open(':memory:');
	}
}
