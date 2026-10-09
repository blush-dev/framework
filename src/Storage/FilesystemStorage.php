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
use Blush\Console\Commands\CreateMissingParents;
use Blush\Console\Commands\CreateMissingTerms;
use Blush\Console\Commands\FileRefs;
use Blush\Console\Commands\FixIds;
use Blush\Console\Commands\MoveToFolders;
use Blush\Console\Commands\RenameToPattern;
use Blush\Content\Index\IndexLocations;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\FilesystemContentWriter;
use Blush\Data\DataStore;
use Blush\Data\FileDataStore;
use Blush\Job\FileJobStore;
use Blush\Media\MediaFiles;
use Blush\Job\JobStore;
use Blush\Session\FileSessionStore;
use Blush\Session\SessionStore;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\Record\RecordStore;

/**
 * Keeps every area in files, as a flat-file site does: Markdown in
 * `user/content`, JSON in `user/data`, and accounts, sessions, and jobs
 * under `storage/` (D-485, D-486), with records in tables kept as their
 * `FileLayout` says (D-643). Content kept as files has tools of its own
 * (D-654): giving files ids, renaming them to their type's pattern and
 * moving them to its folders, filing both forms of their relations,
 * writing a file for each term named without one, and writing the parent
 * pages a tree's folders imply (D-656).
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
			ContentSource::class  => FilesystemSource::class,
			ContentWriter::class  => FilesystemContentWriter::class,
			EntryLocations::class => IndexLocations::class,
			DataStore::class      => FileDataStore::class,
			MediaFiles::class     => MediaFiles::class,
			RecordStore::class    => FileRecordStore::class,
			SessionStore::class   => FileSessionStore::class,
			JobStore::class       => FileJobStore::class
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function commands(StorageArea $area): array
	{
		return $area === StorageArea::Content
			? [FixIds::class, RenameToPattern::class, MoveToFolders::class, FileRefs::class, CreateMissingTerms::class, CreateMissingParents::class]
			: [];
	}
}
