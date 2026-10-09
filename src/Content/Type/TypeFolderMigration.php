<?php

/**
 * Type folder migration.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Closure;
use FilesystemIterator;
use SplFileInfo;
use Throwable;
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentConfig;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Core\Paths;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;

/**
 * Moves the data types that still name their folder into `_` and their
 * name (D-683), for `content:type-folders` and Site Health (D-478's
 * tool): each type's files move from the folder it named to its own,
 * then its `user/data/types/{name}` record is written without the
 * folder, as `LegacyFolder` reads it (a folder pattern becoming
 * `folders`, and a prefix where its addresses came from the folder), so
 * no address changes. Another type's folder inside the one it named
 * stays where it is, for that type's own move.
 *
 * Files move only on a site keeping content in files; the record is
 * written either way. A file whose new path is taken stays, and the type
 * is named in `failed` with its record unwritten, so it's moved again
 * once the clash is cleared. A type from code that names its folder
 * can't be rewritten; it fails to load, saying what to change.
 */
final readonly class TypeFolderMigration
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private DefinitionTables $tables,
		private ContentConfig $config,
		private StorageConfig $storage,
		private Paths $paths,
		private FilesystemWriter $writer,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns the data types that still name their folder: the folder
	 * each names and the one it moves to, by name.
	 *
	 * @return array<string, array{from: string, to: string}>
	 * @throws InvalidContentType
	 */
	public function report(): array
	{
		$types  = ($this->loader)()->load();
		$report = [];

		foreach ($types->namedFolders as $name) {
			$report[$name] = ['from' => LegacyFolder::folderOf($this->definition($name)), 'to' => $types->get($name)->folder];
		}

		return $report;
	}

	/**
	 * Moves them, and returns what was done: how many files and folders
	 * moved for each type, by name, and why any failed.
	 *
	 * @return array{migrated: array<string, int>, failed: array<string, string>}
	 * @throws InvalidContentType When the types can't be read at all.
	 */
	public function migrate(): array
	{
		$report   = $this->report();
		$migrated = [];
		$failed   = [];

		foreach ($report as $name => $folders) {
			$others = array_column(array_diff_key($report, [$name => true]), 'from');

			try {
				$migrated[$name] = $this->migrateOne($name, $folders['from'], $folders['to'], $others);
			} catch (Throwable $error) {
				$failed[$name] = $error->getMessage();
			}
		}

		return ['migrated' => $migrated, 'failed' => $failed];
	}

	/**
	 * Moves one type's files and writes its record, returning how many
	 * files and folders moved.
	 *
	 * @param  list<string> $others The folders other types name, which stay.
	 * @throws InvalidContentType
	 */
	private function migrateOne(string $name, string $from, string $to, array $others): int
	{
		$moves = $from === $to || $from === '' || $this->storage->driverFor(StorageArea::Content) !== StorageConfig::FILESYSTEM
			? []
			: $this->moves($from, $to, $others);

		$done = $this->writer->renameFiles($moves);

		if ($done->failed !== []) {
			throw new InvalidContentType(implode(' ', array_unique(array_values($done->failed))));
		}

		$types = ($this->loader)()->load();
		$kind  = $types->get($name)->kind();
		$code  = $types->origin($name) === TypeOrigin::Extension;

		$this->tables->types()->transaction(function () use ($name, $kind, $code): void {
			$this->tables->types()->save($name, LegacyFolder::convert($name, $this->definition($name), $kind, ! $code && $this->config->dataTypeUrls));

			($this->loader)()->load();
		});

		return count($done->renamed);
	}

	/**
	 * Returns the moves that put what's in a folder into a type's: each
	 * file and folder in it, by path, leaving other types' folders.
	 *
	 * @param  list<string> $others
	 * @return array<string, array<string, string>>
	 */
	private function moves(string $from, string $to, array $others, string $folder = ''): array
	{
		$folder = $folder === '' ? $from : $folder;
		$path   = $this->paths->join($this->paths->content, $folder);
		$moves  = [];

		if (! is_dir($path)) {
			return [];
		}

		foreach (new FilesystemIterator($path) as $item) {
			if (! $item instanceof SplFileInfo) {
				continue;
			}

			$relative = "{$folder}/" . $item->getFilename();

			if (in_array($relative, $others, true)) {
				continue;
			}

			// A folder holding another type's is gone through.
			if (array_any($others, static fn (string $other): bool => str_starts_with($other, "{$relative}/"))) {
				$moves += $this->moves($from, $to, $others, $relative);
				continue;
			}

			$moves[$relative] = [$relative => $to . substr($relative, strlen($from))];
		}

		ksort($moves);

		return $moves;
	}

	/**
	 * Returns a data type's record.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidContentType
	 */
	private function definition(string $name): array
	{
		return $this->tables->types()->find($name) ?? throw new InvalidContentType(sprintf('user/data/types has no "%s".', $name));
	}
}
