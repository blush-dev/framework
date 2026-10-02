<?php

/**
 * Filesystem content writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Cache\ContentVersion;
use Blush\Content\ContentRepository;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\IndexReport;
use Blush\Content\Parser\DocumentFormat;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Support\Slug;

/**
 * Writes content files in `user/content` (D-228):
 *
 * - **Confined:** every path is resolved inside the content folder, and
 *   only content formats (`DocumentFormat`) are written, never anything
 *   executable (D-039).
 * - **Safe:** edits go through `DocumentEditor`, which keeps the file's
 *   formatting and refuses results that wouldn't read back as intended;
 *   files are written atomically, one write at a time (a lock file in
 *   `storage/cache`), and checked against the caller's revision first.
 * - **Copied:** `duplicate()` writes a copy beside an entry, or copies
 *   a bundle's whole folder, never over anything (D-275).
 * - **Kept:** a deleted entry moves to its own folder in `storage/trash`
 *   (`{time}-{random}/`, with a `trash.json` naming it), from where
 *   `restore()` brings it back and `purge()` deletes it for good (D-237).
 * - **Live:** each write reindexes incrementally and moves the content
 *   version on.
 *
 * Front matter keys go through the entry's type schema, so setting
 * `published` updates a file's `date` alias in place.
 */
final readonly class FilesystemWriter implements ContentWriter
{
	/**
	 * The file in each trash folder saying what it holds (D-237).
	 */
	private const string MANIFEST = 'trash.json';

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private DocumentParsers $parsers,
		private DocumentEditor $editor,
		private ContentTypes $types,
		private ContentRepository $content,
		private Indexer $indexer,
		private ContentVersion $version,
		private ClockInterface $clock
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(string $id): EditableEntry
	{
		$path     = $this->path($id);
		$contents = $this->read($path);

		try {
			$document = $this->parsers->parse($id, $contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read: %s', $id, $e->getMessage()), previous: $e);
		}

		clearstatcache(true, $path);
		$modified = @filemtime($path);

		return new EditableEntry($id, $document->frontMatter, $document->body, self::revision($contents), $modified === false ? null : $modified);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null, string $format = 'md'): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		if (DocumentFormat::tryFrom($format) === null) {
			throw new WriteException(sprintf('"%s" isn\'t a content format.', $format));
		}

		$date ??= $this->clock->now();
		$name   = ($type->dateArchives === DateArchives::None ? '' : $date->format('Y-m-d') . '.') . "{$slug}.{$format}";
		$id     = ltrim("{$type->folder}/{$name}", '/');
		$path   = $this->path($id);

		return $this->locked(function () use ($id, $path, $type, $changes): WriteResult {
			if (file_exists($path)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($path)));
			}

			$contents = $this->editor->edit($id, '', $changes, $this->keys($type->name));

			$this->write($path, $contents);

			return new WriteResult($id, self::revision($contents), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes, string $format = 'md'): WriteResult
	{
		if (! array_all(explode('/', $key), static fn (string $segment): bool => Slug::isSlug(ltrim($segment, '_')) && strlen(ltrim($segment, '_')) >= strlen($segment) - 1)) {
			throw new WriteException(sprintf('"%s" isn\'t a page key: slugs separated by "/", each may start with "_".', $key));
		}

		if (DocumentFormat::tryFrom($format) === null) {
			throw new WriteException(sprintf('"%s" isn\'t a content format.', $format));
		}

		$id   = ltrim("{$type->folder}/{$key}.{$format}", '/');
		$path = $this->path($id);

		return $this->locked(function () use ($id, $path, $type, $changes, $format): WriteResult {
			// The page in any format is the same page.
			if (glob(substr($path, 0, -strlen($format) - 1) . '.*') !== []) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($path)));
			}

			$contents = $this->editor->edit($id, '', $changes, $this->keys($type->name));

			$this->write($path, $contents);

			return new WriteResult($id, self::revision($contents), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(string $id, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$path = $this->path($id);

		return $this->locked(function () use ($id, $path, $slug, $changes, $date): WriteResult {
			$contents = $this->read($path);
			$entry    = $this->content->find($id);

			if ($entry?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s, so it can\'t be copied.', $id));
			}

			$prefix = ($date ?? $this->clock->now())->format('Y-m-d') . '.';
			$number = 1;

			do {
				[$from, $to, $newId, $bundle] = $this->duplicateTargets($id, $number === 1 ? $slug : "{$slug}-{$number}", $prefix);
				$number++;
			} while (file_exists($to));

			if ($bundle) {
				$this->copyFolder($from, $to);
			}

			$this->write($this->path($newId), $this->editor->edit($newId, $contents, $changes, $this->keys($entry?->type->name)));

			return new WriteResult($newId, self::revision($this->read($this->path($newId))), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function update(string $id, EntryChanges $changes, ?string $revision = null): WriteResult
	{
		$path = $this->path($id);

		return $this->locked(function () use ($id, $path, $changes, $revision): WriteResult {
			$contents = $this->current($id, $path, $revision);

			if ($changes->isEmpty()) {
				return new WriteResult($id, self::revision($contents), new IndexReport());
			}

			$edited = $this->editor->edit($id, $contents, $changes, $this->keys($this->content->find($id)?->type->name));

			if ($edited !== $contents) {
				$this->write($path, $edited);
			}

			return new WriteResult($id, self::revision($edited), $edited === $contents ? new IndexReport() : $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(string $id, string $slug, ?string $revision = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$path = $this->path($id);

		return $this->locked(function () use ($id, $path, $slug, $revision): WriteResult {
			$contents = $this->current($id, $path, $revision);

			if ($this->content->find($id)?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s.', $id));
			}

			[$from, $to, $newId] = $this->renameTargets($id, $slug);

			if ($from === $to) {
				return new WriteResult($id, self::revision($contents), new IndexReport());
			}

			if (file_exists($to)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($to)));
			}

			if (! @rename($from, $to)) {
				throw new WriteException(sprintf('%s couldn\'t be renamed.', $id));
			}

			return new WriteResult($newId, self::revision($contents), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id, ?string $revision = null): WriteResult
	{
		$path = $this->path($id);

		return $this->locked(function () use ($id, $path, $revision): WriteResult {
			$this->current($id, $path, $revision);

			// A bundle's entry takes its folder (and media) with it.
			$bundle = preg_match('#(^|/)index\.[a-z]+$#', $id) === 1 && $this->content->find($id)?->landing !== true;
			$from   = $bundle ? dirname($path) : $path;
			$now    = $this->clock->now();
			$folder = sprintf('%s/%s-%s', $this->trash(), $now->format('Ymd-His'), bin2hex(random_bytes(3)));
			$target = "{$folder}/" . $this->paths->relative($from);

			if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
				throw new WriteException(sprintf('The trash folder %s couldn\'t be created.', $this->paths->relative(dirname($target))));
			}

			if (! @rename($from, $target)) {
				throw new WriteException(sprintf('%s couldn\'t be moved to the trash.', $id));
			}

			// What was moved, so the trash can list and restore it.
			$this->write("{$folder}/" . self::MANIFEST, json_encode([
				'entry'   => $id,
				'bundle'  => $bundle,
				'trashed' => $now->format(DateTimeInterface::ATOM)
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

			return new WriteResult($id, null, $this->refresh());
		});
	}

	/**
	 * Returns the files a rename moves, and the entry's new id.
	 *
	 * @return array{string, string, string}
	 */
	private function renameTargets(string $id, string $slug): array
	{
		$directory = dirname($id) === '.' ? '' : dirname($id);
		$file      = basename($id);

		if (preg_match('/^index\.([a-z]+)$/', $file) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';
			$newId  = "{$parent}{$slug}/{$file}";

			return [$this->folder($directory), $this->folder("{$parent}{$slug}"), $newId];
		}

		preg_match('/^(\d{4}-\d{2}-\d{2}\.)?.+\.([a-z]+)$/', $file, $match);

		$newId = ltrim("{$directory}/" . ($match[1] ?? '') . "{$slug}." . ($match[2] ?? 'md'), '/');

		return [$this->path($id), $this->path($newId), $newId];
	}

	/**
	 * Returns what a copy copies (the file, or a bundle's folder), where
	 * to, the copy's id, and whether it's a bundle. A leading date in the
	 * name is replaced with `$prefix`.
	 *
	 * @return array{string, string, string, bool}
	 * @throws WriteException
	 */
	private function duplicateTargets(string $id, string $slug, string $prefix): array
	{
		$directory = dirname($id) === '.' ? '' : dirname($id);
		$file      = basename($id);
		$dated     = static fn (string $name): string => preg_match('/^\d{4}-\d{2}-\d{2}\./', $name) === 1 ? $prefix : '';

		if (preg_match('/^index\.([a-z]+)$/', $file) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';
			$folder = $parent . $dated(basename($directory)) . $slug;

			return [$this->folder($directory), $this->folder($folder), "{$folder}/{$file}", true];
		}

		preg_match('/^.+\.([a-z]+)$/', $file, $match);

		$newId = ltrim("{$directory}/" . $dated($file) . "{$slug}." . ($match[1] ?? 'md'), '/');

		return [$this->path($id), $this->path($newId), $newId, false];
	}

	/**
	 * Copies a folder and everything in it to a new place.
	 *
	 * @throws WriteException
	 */
	private function copyFolder(string $from, string $to): void
	{
		if (! @mkdir($to, 0775, true)) {
			throw new WriteException(sprintf('The folder %s couldn\'t be created.', $this->paths->relative($to)));
		}

		$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

		foreach ($items as $item) {
			if (! $item instanceof SplFileInfo || $item->isLink()) {
				continue;
			}

			$target = $to . substr($item->getPathname(), strlen($from));
			$copied = $item->isDir() ? (is_dir($target) || @mkdir($target, 0775)) : @copy($item->getPathname(), $target);

			if (! $copied) {
				throw new WriteException(sprintf('%s couldn\'t be copied.', $this->paths->relative($item->getPathname())));
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trashed(): array
	{
		$entries = [];

		foreach (glob($this->trash() . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
			array_push($entries, ...$this->trashedIn($folder));
		}

		usort($entries, static fn (TrashedEntry $a, TrashedEntry $b): int => [$b->trashed, $a->entry] <=> [$a->trashed, $b->entry]);

		return $entries;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function loadTrashed(string $trashId): EditableEntry
	{
		$trashed  = $this->findTrashed($trashId);
		$file     = $this->trashedFile($trashed);
		$contents = $this->read($file);

		try {
			$document = $this->parsers->parse($trashed->entry, $contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read: %s', $trashed->entry, $e->getMessage()), previous: $e);
		}

		return new EditableEntry($trashed->entry, $document->frontMatter, $document->body, self::revision($contents), $trashed->trashed->getTimestamp());
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(string $trashId, EntryChanges $changes = new EntryChanges()): WriteResult
	{
		return $this->locked(function () use ($trashId, $changes): WriteResult {
			$trashed = $this->findTrashed($trashId);
			$file    = $this->trashedFile($trashed);
			$path    = $this->path($trashed->entry);
			$from    = $trashed->bundle ? dirname($file) : $file;
			$to      = $trashed->bundle ? dirname($path) : $path;

			if (file_exists($to)) {
				throw new WriteException(sprintf('%s can\'t be restored: something else is there now. Move or rename it, then try again.', $this->paths->relative($to)));
			}

			if (! $changes->isEmpty()) {
				$type = null;

				try {
					$type = $this->types->forFile($trashed->entry)->name;
				} catch (InvalidContentType) {
					// No type claims it; keys are used as given.
				}

				$this->write($file, $this->editor->edit($trashed->entry, $this->read($file), $changes, $this->keys($type)));
			}

			if (! is_dir(dirname($to)) && ! @mkdir(dirname($to), 0775, true) && ! is_dir(dirname($to))) {
				throw new WriteException(sprintf('The folder %s couldn\'t be created.', $this->paths->relative(dirname($to))));
			}

			if (! @rename($from, $to)) {
				throw new WriteException(sprintf('%s couldn\'t be moved back from the trash.', $trashed->entry));
			}

			$this->tidyTrash($trashId);

			return new WriteResult($trashed->entry, self::revision($this->read($path)), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function purge(string $trashId): void
	{
		$this->locked(function () use ($trashId): void {
			$trashed = $this->findTrashed($trashId);
			$file    = $this->trashedFile($trashed);

			self::remove($trashed->bundle ? dirname($file) : $file);
			$this->tidyTrash($trashId);
		});
	}

	/**
	 * Returns the trash folder.
	 */
	private function trash(): string
	{
		return "{$this->paths->storage}/trash";
	}

	/**
	 * Lists what one trash folder holds: the entry its manifest names, or,
	 * for trash from before manifests (D-237), each content file in it.
	 *
	 * @return list<TrashedEntry>
	 */
	private function trashedIn(string $folder): array
	{
		$name     = basename($folder);
		$manifest = $this->manifest($folder);

		if ($manifest !== null) {
			return [$this->describeTrashed($name, $manifest['entry'], $manifest['bundle'], $manifest['trashed'])];
		}

		$trashed = DateTimeImmutable::createFromFormat('!Ymd-His', substr($name, 0, 15)) ?: new DateTimeImmutable('@' . (int) filemtime($folder));
		$root    = "{$folder}/" . $this->paths->relative($this->paths->content);
		$entries = [];

		foreach ($this->filesystem->files($root, links: false) as $entry => $file) {
			if ($this->parsers->supports($entry)) {
				$entries[] = $this->describeTrashed($name, $entry, false, $trashed);
			}
		}

		return $entries;
	}

	/**
	 * Reads a trash folder's manifest, or returns `null` without one.
	 *
	 * @return ?array{entry: string, bundle: bool, trashed: DateTimeImmutable}
	 */
	private function manifest(string $folder): ?array
	{
		$contents = @file_get_contents("{$folder}/" . self::MANIFEST);
		$data     = $contents === false ? null : json_decode($contents, true);

		if (! is_array($data) || ! is_string($data['entry'] ?? null) || ! is_bool($data['bundle'] ?? null) || ! is_string($data['trashed'] ?? null)) {
			return null;
		}

		$trashed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['trashed']);

		return $trashed === false ? null : ['entry' => $data['entry'], 'bundle' => $data['bundle'], 'trashed' => $trashed];
	}

	/**
	 * Describes a trashed entry, reading its front matter when it can.
	 */
	private function describeTrashed(string $folder, string $entry, bool $bundle, DateTimeImmutable $trashed): TrashedEntry
	{
		$described = new TrashedEntry("{$folder}/{$entry}", $entry, $bundle, $trashed, []);
		$contents  = @file_get_contents($this->trashedFile($described));

		try {
			$frontMatter = $contents === false ? [] : $this->parsers->parse($entry, $contents)->frontMatter;
		} catch (InvalidDocument) {
			$frontMatter = [];
		}

		return new TrashedEntry($described->id, $entry, $bundle, $trashed, $frontMatter);
	}

	/**
	 * Finds a trashed entry by the trash's name for it.
	 *
	 * @throws WriteException
	 */
	private function findTrashed(string $trashId): TrashedEntry
	{
		[$folder] = explode('/', $trashId, 2);

		if (preg_match('/^\d{8}-\d{6}(-[0-9a-f]{6})?$/', $folder) === 1 && is_dir($this->trash() . "/{$folder}")) {
			foreach ($this->trashedIn($this->trash() . "/{$folder}") as $trashed) {
				if ($trashed->id === $trashId) {
					return $trashed;
				}
			}
		}

		throw new WriteException(sprintf('There\'s no "%s" in the trash.', $trashId));
	}

	/**
	 * Returns the path of a trashed entry's file, confined to its folder.
	 *
	 * @throws WriteException
	 */
	private function trashedFile(TrashedEntry $trashed): string
	{
		[$folder] = explode('/', $trashed->id, 2);

		try {
			return $this->paths->join($this->trash() . "/{$folder}/" . $this->paths->relative($this->paths->content), $trashed->entry);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('"%s" is outside the trash.', $trashed->id), previous: $e);
		}
	}

	/**
	 * Removes a trash folder's manifest once its entry is gone, then any
	 * folders left empty, the trash folder itself included.
	 */
	private function tidyTrash(string $trashId): void
	{
		[$name] = explode('/', $trashId, 2);
		$folder = $this->trash() . "/{$name}";

		if (is_file("{$folder}/" . self::MANIFEST)) {
			@unlink("{$folder}/" . self::MANIFEST);
		}

		$directories = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($directories as $item) {
			if ($item instanceof SplFileInfo && $item->isDir() && ! $item->isLink()) {
				@rmdir($item->getPathname());
			}
		}

		@rmdir($folder);
	}

	/**
	 * Deletes a file, or a folder and everything in it.
	 *
	 * @throws WriteException
	 */
	private static function remove(string $path): void
	{
		if (is_dir($path) && ! is_link($path)) {
			$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

			foreach ($items as $item) {
				if ($item instanceof SplFileInfo) {
					$item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
				}
			}

			$removed = @rmdir($path);
		} else {
			$removed = @unlink($path);
		}

		if (! $removed) {
			throw new WriteException(sprintf('%s couldn\'t be deleted.', basename($path)));
		}
	}

	/**
	 * Returns a file's contents after checking the revision.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	private function current(string $id, string $path, ?string $revision): string
	{
		$contents = $this->read($path);

		if ($revision !== null && ! hash_equals(self::revision($contents), $revision)) {
			throw new WriteConflict(sprintf('%s changed since it was opened. Reload it, then make your change again.', $id));
		}

		return $contents;
	}

	/**
	 * Returns the absolute path for an id, confined to the content folder
	 * and to content formats.
	 *
	 * @throws WriteException
	 */
	private function path(string $id): string
	{
		if ($id === '' || str_starts_with($id, '/') || ! $this->parsers->supports($id) || str_contains($id, "\0")) {
			throw new WriteException(sprintf('"%s" isn\'t a content file.', $id));
		}

		try {
			return $this->paths->join($this->paths->content, $id);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('"%s" is outside the content folder.', $id), previous: $e);
		}
	}

	/**
	 * Returns the absolute path of a folder in the content folder.
	 *
	 * @throws WriteException
	 */
	private function folder(string $relative): string
	{
		try {
			return $this->paths->join($this->paths->content, $relative);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('"%s" is outside the content folder.', $relative), previous: $e);
		}
	}

	/**
	 * Reads a file.
	 *
	 * @throws WriteException
	 */
	private function read(string $path): string
	{
		$contents = is_file($path) ? @file_get_contents($path) : false;

		return $contents === false
			? throw new WriteException(sprintf('There\'s no content file %s.', $this->paths->relative($path)))
			: $contents;
	}

	/**
	 * Writes a file atomically.
	 *
	 * @throws WriteException
	 */
	private function write(string $path, string $contents): void
	{
		try {
			$this->filesystem->writeAtomic($path, $contents);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('%s couldn\'t be written: %s', $this->paths->relative($path), $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns a closure giving a field name's keys (the name, then its
	 * aliases) under a type's schema, or just the name without one.
	 *
	 * @return Closure(string): list<string>
	 */
	private function keys(?string $type): Closure
	{
		$schema = $type === null || $this->types->find($type) === null ? null : $this->types->schema($type);

		return static function (string $name) use ($schema): array {
			$field = $schema?->field($name);

			return $field === null ? [$name] : array_values(array_unique([$name, $field->name, ...$field->aliases]));
		};
	}

	/**
	 * Reindexes and moves the content version on.
	 */
	private function refresh(): IndexReport
	{
		$report = $this->indexer->index();
		$this->version->bump();

		return $report;
	}

	/**
	 * Runs a write while holding the content write lock.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws WriteException When the lock can't be taken.
	 */
	private function locked(Closure $write): mixed
	{
		$file = "{$this->paths->cache}/content-write.lock";

		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$handle = @fopen($file, 'c');

		if ($handle === false || ! flock($handle, LOCK_EX)) {
			throw new WriteException('Content can\'t be written right now: the write lock couldn\'t be taken.');
		}

		try {
			return $write();
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/**
	 * Returns a file's revision: a hash of its contents.
	 */
	private static function revision(string $contents): string
	{
		return hash('sha256', $contents);
	}
}
