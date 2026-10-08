<?php

/**
 * File record store conformance test, one-file tables.
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
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\RecordStore;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(FileRecordStore::class)]
#[CoversClass(FileLayouts::class)]
final class FileRecordStoreOneFileTest extends RecordStoreConformance
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	#[Override]
	protected function store(): RecordStore
	{
		$paths   = Paths::fromRoot($this->temporaryDirectory());
		$layouts = new FileLayouts($paths);

		$layouts->register('albums', FileLayout::oneFile("{$paths->data}/albums.json", 'albums'));
		$layouts->register('notes', FileLayout::oneFile("{$paths->data}/notes.json"));

		return new FileRecordStore($paths, $layouts, new FileTransactions($paths, new Filesystem()), new Filesystem());
	}
}
