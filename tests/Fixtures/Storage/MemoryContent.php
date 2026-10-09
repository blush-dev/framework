<?php

/**
 * A storage driver that keeps records in memory, for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Storage;

use Override;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Storage;
use Blush\Storage\StorageArea;

final readonly class MemoryContent implements Storage
{
	#[Override]
	public function bindings(): array
	{
		return [RecordStore::class => ArrayRecordStore::class];
	}

	#[Override]
	public function commands(StorageArea $area): array
	{
		return [];
	}
}
