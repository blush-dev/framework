<?php

/**
 * File record store conformance test, folder tables.
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
use Blush\Core\Paths;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\RecordStore;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(FileRecordStore::class)]
#[CoversClass(FileTransactions::class)]
final class FileRecordStoreFolderTest extends RecordStoreConformance
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	#[Override]
	protected function store(): RecordStore
	{
		$paths = Paths::fromRoot($this->temporaryDirectory());

		return new FileRecordStore($paths, new FileLayouts($paths), new FileTransactions($paths, new Filesystem()), new Filesystem());
	}
}
