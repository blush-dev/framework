<?php

/**
 * Filesystem storage.
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
use Blush\Auth\FileAccountStore;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Data\DataStore;
use Blush\Data\FileDataStore;
use Blush\Job\FileJobStore;
use Blush\Job\JobStore;
use Blush\Session\FileSessionStore;
use Blush\Session\SessionStore;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\Record\RecordStore;

/**
 * Keeps every area in files, as a flat-file site does: Markdown in
 * `user/content`, JSON in `user/data`, and accounts, sessions, and jobs
 * under `storage/` (D-485, D-486), with records in tables kept as their
 * `FileLayout` says (D-643).
 */
final readonly class FilesystemStorage implements Storage
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function bindings(): array
	{
		return [
			ContentSource::class => FilesystemSource::class,
			ContentWriter::class => FilesystemWriter::class,
			DataStore::class     => FileDataStore::class,
			RecordStore::class   => FileRecordStore::class,
			AccountStore::class  => FileAccountStore::class,
			SessionStore::class  => FileSessionStore::class,
			JobStore::class      => FileJobStore::class
		];
	}
}
