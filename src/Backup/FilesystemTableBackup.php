<?php

/**
 * Filesystem table backup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Override;
use Blush\Content\Record\EntryTable;
use Blush\Core\Paths;
use Blush\Job\JobStatus;
use Blush\Job\RecordJobStore;
use Blush\Media\MediaMetadataStore;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;
use Blush\Support\Filesystem;

/**
 * The filesystem driver's backups (D-882): each table's files as they're
 * kept, at their paths from the site's root under `filesystem/` in the
 * archive, so a backup is a readable copy of the site's own files. Most
 * tables are where `FileLayouts` puts them (a folder, or one file); the
 * rest are kept by stores of their own:
 *
 * - **Entries** are `user/content` whole, and their relations (`refs`)
 *   are in its files' front matter, so they have no files of their own.
 * - **Media details** are `user/data/media`, the metadata files beside
 *   no originals.
 * - **Jobs** are `storage/jobs`, the schedule's state with them, without
 *   the jobs that hadn't finished.
 *
 * Files are copied `BATCH` at a time. Restoring removes a table's files
 * and copies the backup's in their place (`restore()`).
 */
final readonly class FilesystemTableBackup implements TableBackup
{
	public const string DRIVER = StorageConfig::FILESYSTEM;

	/**
	 * The most files copied in one chunk.
	 */
	public const int BATCH = 500;

	public function __construct(
		private Paths $paths,
		private FileLayouts $layouts,
		private Filesystem $filesystem = new Filesystem()
	) {}

	#[Override]
	public function driver(): string
	{
		return self::DRIVER;
	}

	#[Override]
	public function bytes(Table $table): int
	{
		$bytes = 0;

		foreach ($this->files($table) as $path) {
			$bytes += (int) @filesize($path);
		}

		return $bytes;
	}

	#[Override]
	public function copy(Table $table, string $work, int $cursor): ?int
	{
		$files = $this->files($table);
		$batch = array_slice($files, $cursor, self::BATCH);

		foreach ($batch as $path) {
			$target = "{$work}/" . self::DRIVER . '/' . $this->paths->relative($path);

			if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0o770, true) && ! is_dir(dirname($target))) {
				throw new BackupException(sprintf('The folder %s couldn\'t be made.', dirname($target)));
			}

			// A file removed since the list was made is simply not in it.
			if (! @copy($path, $target) && is_file($path)) {
				throw new BackupException(sprintf('%s couldn\'t be copied.', $this->paths->relative($path)));
			}
		}

		$next = $cursor + count($batch);

		return $next < count($files) ? $next : null;
	}

	#[Override]
	public function restore(Table $table, string $work, int $cursor): ?int
	{
		$root = $this->root($table);

		if ($root === null || ! str_starts_with($root, $this->paths->root . '/')) {
			return null;
		}

		// The table's files now go first, so a file the backup doesn't hold
		// doesn't stay behind.
		if ($cursor === 0) {
			foreach ($this->files($table) as $path) {
				if (! @unlink($path) && is_file($path)) {
					throw new BackupException(sprintf('%s couldn\'t be replaced.', $this->paths->relative($path)));
				}
			}
		}

		$source = "{$work}/" . self::DRIVER . '/' . $this->paths->relative($root);
		$files  = $this->held($table, $source);
		$batch  = array_slice($files, $cursor, self::BATCH);

		foreach ($batch as $relative) {
			$from   = $relative === '' ? $source : "{$source}/{$relative}";
			$target = $relative === '' ? $root : "{$root}/{$relative}";

			if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0o775, true) && ! is_dir(dirname($target))) {
				throw new BackupException(sprintf('The folder %s couldn\'t be made.', $this->paths->relative(dirname($target))));
			}

			if (! @copy($from, $target)) {
				throw new BackupException(sprintf('%s couldn\'t be restored.', $this->paths->relative($target)));
			}
		}

		$next = $cursor + count($batch);

		return $next < count($files) ? $next : null;
	}

	#[Override]
	public function finish(array $tables, string $work): void
	{
	}

	/**
	 * Returns a table's files in an unzipped backup, by path from where
	 * the table is kept (`''` for a table kept as one file), in order.
	 *
	 * @return list<string>
	 */
	private function held(Table $table, string $source): array
	{
		if (is_file($source)) {
			return [''];
		}

		$files = [];

		foreach ($this->filesystem->files($source, links: false) as $relative => $file) {
			if (! ($table->name === RecordJobStore::TABLE && $this->unfinished($relative))) {
				$files[] = $relative;
			}
		}

		sort($files);

		return $files;
	}

	/**
	 * Returns a table's files, in order, by full path. Only files inside
	 * the site's root are listed, since the archive keeps paths from it.
	 *
	 * @return list<string>
	 */
	private function files(Table $table): array
	{
		$root = $this->root($table);

		if ($root === null || ! str_starts_with($root, $this->paths->root . '/')) {
			return [];
		}

		if (is_file($root)) {
			return [$root];
		}

		$files = [];

		foreach ($this->filesystem->files($root, links: false) as $relative => $file) {
			if ($table->name === RecordJobStore::TABLE && $this->unfinished($relative)) {
				continue;
			}

			$files[] = "{$root}/{$relative}";
		}

		sort($files);

		return $files;
	}

	/**
	 * Returns the file or folder a table is kept in, or `null` for one
	 * kept in another table's files.
	 */
	private function root(Table $table): ?string
	{
		return match (true) {
			$table->area === StorageArea::Content && $table->name === EntryTable::TABLE => $this->paths->content,
			$table->area === StorageArea::Content && $table->name === Ref::TABLE        => null,
			$table->name === MediaMetadataStore::TABLE                                  => "{$this->paths->data}/" . MediaMetadataStore::FOLDER,
			$table->name === RecordJobStore::TABLE                                      => $this->paths->jobs,
			$table->name === RecordJobStore::STATE                                      => null,
			default                                                                     => $this->layout($table)
		};
	}

	/**
	 * Returns where `FileLayouts` keeps a table, or `null` when it can't.
	 */
	private function layout(Table $table): ?string
	{
		try {
			return $this->layouts->for($table)->path;
		} catch (InvalidRecord) {
			return null;
		}
	}

	/**
	 * Returns whether a file in the jobs folder is a job that hasn't
	 * finished, which a restore would run again.
	 */
	private function unfinished(string $relative): bool
	{
		return str_starts_with($relative, JobStatus::Queued->value . '/') || str_starts_with($relative, JobStatus::Running->value . '/');
	}
}
