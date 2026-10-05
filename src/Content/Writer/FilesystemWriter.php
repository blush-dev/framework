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
use Blush\Content\EntryFields;
use Blush\Content\Status;
use Blush\Content\Parser\DocumentFormat;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Tree;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Support\Slug;
use Blush\Support\Uuid;

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
 * - **Nested:** `createUnder()` writes a tree's page in its parent's
 *   folder, first making a parent kept as a file its folder's page
 *   (D-408), and moving it back if the page can't be written; `move()`
 *   moves a page and the pages under it to another parent (D-410),
 *   undoing every step if one fails.
 * - **Copied:** `duplicate()` writes a copy beside an entry, or copies
 *   a bundle's whole folder, never over anything (D-275).
 * - **Kept:** a trashed entry stays where it is, marked `status: trash`
 *   (D-484), until `restore()` makes it a draft or `delete()` removes it.
 * - **Live:** each write reindexes incrementally and moves the content
 *   version on.
 *
 * Front matter keys go through the entry's type schema, so setting
 * `published` updates a file's `date` alias in place.
 */
final readonly class FilesystemWriter implements ContentWriter
{
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
	public function load(string $path): EditableEntry
	{
		$file     = $this->file($path);
		$contents = $this->read($file);

		try {
			$document = $this->parsers->parse($path, $contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read: %s', $path, $e->getMessage()), previous: $e);
		}

		clearstatcache(true, $file);
		$modified = @filemtime($file);

		return new EditableEntry($path, $document->frontMatter, $document->body, self::revision($contents), $modified === false ? null : $modified);
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
		$path     = ltrim("{$type->folder}/{$name}", '/');
		$file   = $this->file($path);

		return $this->locked(function () use ($path, $file, $type, $changes): WriteResult {
			if (file_exists($file)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($file)));
			}

			$contents = $this->editor->edit($path, '', $this->withId($changes), $this->keys($type->name));

			$this->write($file, $contents);

			return new WriteResult($path, self::revision($contents), $this->refresh());
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

		$path   = ltrim("{$type->folder}/{$key}.{$format}", '/');
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $type, $changes, $format): WriteResult {
			// The page in any format is the same page.
			if (glob(substr($file, 0, -strlen($format) - 1) . '.*') !== []) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($file)));
			}

			$contents = $this->editor->edit($path, '', $this->withId($changes), $this->keys($type->name));

			$this->write($file, $contents);

			return new WriteResult($path, self::revision($contents), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createUnder(string $parentPath, string $slug, EntryChanges $changes, string $format = 'md'): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		if (DocumentFormat::tryFrom($format) === null) {
			throw new WriteException(sprintf('"%s" isn\'t a content format.', $format));
		}

		return $this->locked(function () use ($parentPath, $slug, $changes, $format): WriteResult {
			$parent = $this->content->findPath($parentPath);

			if ($parent === null || ! $parent->type instanceof Tree) {
				throw new WriteException(sprintf('%s isn\'t a page other pages can go under.', $parentPath));
			}

			if ($parent->landing) {
				throw new WriteException(sprintf('%s is an index page; pages under it go at the top.', $parentPath));
			}

			$type   = $parent->type;
			$folder = ltrim("{$type->folder}/{$parent->key}", '/');
			$path     = "{$folder}/{$slug}.{$format}";
			$file   = $this->file($path);

			if (
				$this->content->named($type->name, "{$parent->key}/{$slug}") !== null
				|| glob(substr($file, 0, -strlen($format) - 1) . '.*') !== []
				|| glob($this->folder("{$folder}/{$slug}") . '/index.*') !== []
			) {
				throw new WriteException(sprintf('There\'s already a page at %s/%s.', $parent->key, $slug));
			}

			// Edited before anything moves, so a refused edit moves nothing.
			$contents  = $this->editor->edit($path, '', $this->withId($changes), $this->keys($type->name));
			$extension = pathinfo($parent->path, PATHINFO_EXTENSION);
			$moved     = [];
			$made      = ! is_dir($this->folder($folder));

			if ($parent->path === "{$folder}.{$extension}") {
				$moved = [$parent->path => $this->promote($parent->path, $folder, $extension)];
			}

			try {
				$this->write($file, $contents);
			} catch (WriteException $e) {
				foreach ($moved as $from => $to) {
					@rename($this->file($to), $this->file($from));
				}

				if ($made) {
					@rmdir($this->folder($folder));
				}

				throw $e;
			}

			return new WriteResult($path, self::revision($contents), $this->refresh(), $moved);
		});
	}

	/**
	 * Makes a page kept as a file its folder's page (D-408): `about.md`
	 * becomes `about/index.md`, the folder made if it isn't there.
	 * Returns its new path.
	 *
	 * @throws WriteException When the folder already has a page.
	 */
	private function promote(string $path, string $folder, string $extension): string
	{
		$directory = $this->folder($folder);

		if (glob("{$directory}/index.*") !== []) {
			throw new WriteException(sprintf('%s can\'t move into %s/: that folder already has a page. Remove one of the two, then try again.', $path, $folder));
		}

		$made = ! is_dir($directory);

		if ($made && ! @mkdir($directory, 0775, true)) {
			throw new WriteException(sprintf('The folder %s couldn\'t be created.', $this->paths->relative($directory)));
		}

		$newPath = "{$folder}/index.{$extension}";

		if (! @rename($this->file($path), $this->file($newPath))) {
			if ($made) {
				@rmdir($directory);
			}

			throw new WriteException(sprintf('%s couldn\'t be moved to %s.', $path, $newPath));
		}

		return $newPath;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(string $path, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $slug, $changes, $date): WriteResult {
			$contents = $this->read($file);
			$entry    = $this->content->findPath($path);

			if ($entry?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s, so it can\'t be copied.', $path));
			}

			$prefix = ($date ?? $this->clock->now())->format('Y-m-d') . '.';
			$number = 1;

			do {
				[$from, $to, $newPath, $bundle] = $this->duplicateTargets($path, $number === 1 ? $slug : "{$slug}-{$number}", $prefix);
				$number++;
			} while (file_exists($to));

			if ($bundle) {
				$this->copyFolder($from, $to);
			}

			$this->write($this->file($newPath), $this->editor->edit($newPath, $contents, $this->withId($changes), $this->keys($entry?->type->name)));

			return new WriteResult($newPath, self::revision($this->read($this->file($newPath))), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function update(string $path, EntryChanges $changes, ?string $revision = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $changes, $revision): WriteResult {
			$contents = $this->current($path, $file, $revision);

			if ($changes->isEmpty()) {
				return new WriteResult($path, self::revision($contents), new IndexReport());
			}

			// The id isn't changed by an edit, but one is given to a file without it.
			$changes = $this->hasId($path, $contents) ? self::withoutId($changes) : $this->withId($changes);
			$edited  = $this->editor->edit($path, $contents, $changes, $this->keys($this->content->findPath($path)?->type->name));

			if ($edited !== $contents) {
				$this->write($file, $edited);
			}

			return new WriteResult($path, self::revision($edited), $edited === $contents ? new IndexReport() : $this->refresh([$path]));
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rename(string $path, string $slug, ?string $revision = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $slug, $revision): WriteResult {
			$contents = $this->current($path, $file, $revision);

			if ($this->content->findPath($path)?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s.', $path));
			}

			[$from, $to, $newPath] = $this->renameTargets($path, $slug);

			if ($from === $to) {
				return new WriteResult($path, self::revision($contents), new IndexReport());
			}

			if (file_exists($to)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($to)));
			}

			if (! @rename($from, $to)) {
				throw new WriteException(sprintf('%s couldn\'t be renamed.', $path));
			}

			return new WriteResult($newPath, self::revision($contents), $this->refresh());
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(string $path, ?string $parentPath, ?string $revision = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $parentPath, $revision): WriteResult {
			$contents = $this->current($path, $file, $revision);
			$entry    = $this->content->findPath($path);

			if ($entry === null || ! $entry->type instanceof Tree) {
				throw new WriteException(sprintf('%s isn\'t a page that can move.', $path));
			}

			if ($entry->landing) {
				throw new WriteException(sprintf('%s is an index page; it stays at the top of its folder.', $path));
			}

			$type   = $entry->type;
			$parent = $parentPath === null ? null : $this->content->findPath($parentPath);

			if ($parentPath !== null && ($parent === null || $parent->type->name !== $type->name || $parent->landing)) {
				throw new WriteException(sprintf('%s isn\'t one of the %s a page can go under.', $parentPath, $type->labels->items));
			}

			if ($parent !== null && ($parent->key === $entry->key || str_starts_with($parent->key, "{$entry->key}/"))) {
				throw new WriteException(sprintf('%s can\'t go under itself or a page under it.', $entry->title === '' ? $path : $entry->title));
			}

			if ($type->parentKey($entry->key, []) === $parent?->key) {
				return new WriteResult($path, self::revision($contents), new IndexReport());
			}

			$slug   = basename($entry->key);
			$newKey = $parent === null ? $slug : "{$parent->key}/{$slug}";
			$target = ltrim($type->folder . ($parent === null ? '' : "/{$parent->key}"), '/');

			if ($this->content->named($type->name, $newKey) !== null) {
				throw new WriteException(sprintf('There\'s already a page at %s.', $newKey));
			}

			// What moves: the page's file or folder, and the folder its key
			// names, which holds the pages under a page kept as a file.
			$bundle = basename($path, '.' . pathinfo($path, PATHINFO_EXTENSION)) === 'index';
			$items  = [$bundle ? dirname($path) : $path];
			$folder = ltrim("{$type->folder}/{$entry->key}", '/');

			if (! $bundle && is_dir($this->folder($folder))) {
				$items[] = $folder;
			}

			$plan = [];

			foreach ($items as $item) {
				$to = ltrim("{$target}/" . basename($item), '/');

				if (file_exists($this->folder($to))) {
					throw new WriteException(sprintf('%s already exists.', $to));
				}

				$plan[$item] = $to;
			}

			// Every entry that moves, old path to new.
			$moved = [];

			foreach ($plan as $from => $to) {
				if (! is_dir($this->folder($from))) {
					$moved[$from] = $to;

					continue;
				}

				foreach ($this->filesystem->files($this->folder($from), links: false) as $relative => $absolute) {
					if ($this->parsers->supports($relative)) {
						$moved["{$from}/{$relative}"] = "{$to}/{$relative}";
					}
				}
			}

			ksort($moved);

			$done = [];
			$made = ! is_dir($this->folder($target));

			try {
				if ($parent !== null && $parent->path === "{$target}." . pathinfo($parent->path, PATHINFO_EXTENSION)) {
					$promoted = $this->promote($parent->path, $target, pathinfo($parent->path, PATHINFO_EXTENSION));
					$done[]   = [$this->file($parent->path), $this->file($promoted)];
					$moved    = [$parent->path => $promoted, ...$moved];
				}

				foreach ($plan as $from => $to) {
					if (! @rename($this->folder($from), $this->folder($to))) {
						throw new WriteException(sprintf('%s couldn\'t be moved to %s.', $from, $to));
					}

					$done[] = [$this->folder($from), $this->folder($to)];
				}
			} catch (WriteException $e) {
				foreach (array_reverse($done) as [$from, $to]) {
					@rename($to, $from);
				}

				if ($made) {
					@rmdir($this->folder($target));
				}

				throw $e;
			}

			$newPath = $bundle ? "{$plan[dirname($path)]}/" . basename($path) : $plan[$path];

			return new WriteResult($newPath, self::revision($contents), $this->refresh(), $moved);
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(string $path, ?string $revision = null): WriteResult
	{
		return $this->update($path, new EntryChanges([
			'status'              => Status::Trash->value,
			EntryFields::TRASHED => $this->clock->now()->format('Y-m-d H:i:s P')
		]), $revision);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(string $path, ?string $revision = null): WriteResult
	{
		return $this->update($path, new EntryChanges(['status' => Status::Draft->value], [EntryFields::TRASHED]), $revision);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $path, ?string $revision = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $revision): WriteResult {
			$this->current($path, $file, $revision);

			$bundle = preg_match('#(^|/)index\.[a-z]+$#', $path) === 1 && $this->content->findPath($path)?->landing !== true;

			if (! @unlink($file)) {
				throw new WriteException(sprintf('%s couldn\'t be deleted.', $path));
			}

			// A bundle's folder goes with its entry once it's empty; pages
			// kept under a tree's page stay.
			if ($bundle) {
				@rmdir(dirname($file));
			}

			return new WriteResult($path, null, $this->refresh());
		});
	}

	/**
	 * Returns the files a rename moves, and the entry's new path.
	 *
	 * @return array{string, string, string}
	 */
	private function renameTargets(string $path, string $slug): array
	{
		$directory = dirname($path) === '.' ? '' : dirname($path);
		$file      = basename($path);

		if (preg_match('/^index\.([a-z]+)$/', $file) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';
			$newPath  = "{$parent}{$slug}/{$file}";

			return [$this->folder($directory), $this->folder("{$parent}{$slug}"), $newPath];
		}

		preg_match('/^(\d{4}-\d{2}-\d{2}\.)?.+\.([a-z]+)$/', $file, $match);

		$newPath = ltrim("{$directory}/" . ($match[1] ?? '') . "{$slug}." . ($match[2] ?? 'md'), '/');

		return [$this->file($path), $this->file($newPath), $newPath];
	}

	/**
	 * Returns what a copy copies (the file, or a bundle's folder), where
	 * to, the copy's path, and whether it's a bundle. A leading date in the
	 * name is replaced with `$prefix`.
	 *
	 * @return array{string, string, string, bool}
	 * @throws WriteException
	 */
	private function duplicateTargets(string $path, string $slug, string $prefix): array
	{
		$directory = dirname($path) === '.' ? '' : dirname($path);
		$file      = basename($path);
		$dated     = static fn (string $name): string => preg_match('/^\d{4}-\d{2}-\d{2}\./', $name) === 1 ? $prefix : '';

		if (preg_match('/^index\.([a-z]+)$/', $file) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';
			$folder = $parent . $dated(basename($directory)) . $slug;

			return [$this->folder($directory), $this->folder($folder), "{$folder}/{$file}", true];
		}

		preg_match('/^.+\.([a-z]+)$/', $file, $match);

		$newPath = ltrim("{$directory}/" . $dated($file) . "{$slug}." . ($match[1] ?? 'md'), '/');

		return [$this->file($path), $this->file($newPath), $newPath, false];
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
	 * Returns a file's contents after checking the revision.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	private function current(string $path, string $file, ?string $revision): string
	{
		$contents = $this->read($file);

		if ($revision !== null && ! hash_equals(self::revision($contents), $revision)) {
			throw new WriteConflict(sprintf('%s changed since it was opened. Reload it, then make your change again.', $path));
		}

		return $contents;
	}

	/**
	 * Returns the absolute path for an entry's path, confined to the content folder
	 * and to content formats.
	 *
	 * @throws WriteException
	 */
	private function file(string $path): string
	{
		if ($path === '' || str_starts_with($path, '/') || ! $this->parsers->supports($path) || str_contains($path, "\0")) {
			throw new WriteException(sprintf('"%s" isn\'t a content file.', $path));
		}

		try {
			return $this->paths->join($this->paths->content, $path);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('"%s" is outside the content folder.', $path), previous: $e);
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
	private function read(string $file): string
	{
		$contents = is_file($file) ? @file_get_contents($file) : false;

		return $contents === false
			? throw new WriteException(sprintf('There\'s no content file %s.', $this->paths->relative($file)))
			: $contents;
	}

	/**
	 * Writes a file atomically.
	 *
	 * @throws WriteException
	 */
	private function write(string $file, string $contents): void
	{
		try {
			$this->filesystem->writeAtomic($file, $contents);
		} catch (FilesystemException $e) {
			throw new WriteException(sprintf('%s couldn\'t be written: %s', $this->paths->relative($file), $e->getMessage()), previous: $e);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function assignIds(array $paths): AssignedIds
	{
		return $this->locked(function () use ($paths): AssignedIds {
			$ids    = [];
			$failed = [];

			foreach (array_unique($paths) as $path) {
				try {
					$file     = $this->file($path);
					$contents = $this->read($file);
					$id       = Uuid::v7($this->clock->now());
					$changes  = new EntryChanges([EntryFields::ID => $id]);

					$this->write($file, $this->editor->edit($path, $contents, $changes, $this->keys($this->content->findPath($path)?->type->name)));

					$ids[$path] = $id;
				} catch (WriteException $e) {
					$failed[$path] = $e->getMessage();
				}
			}

			return new AssignedIds($ids, $failed, $ids === [] ? new IndexReport() : $this->refresh(array_keys($ids)));
		});
	}

	/**
	 * Returns changes with a new id (D-477) set last, so a file that has
	 * none gets it at the end of its front matter, and one that has one
	 * (a copy) gets it in its place.
	 */
	private function withId(EntryChanges $changes): EntryChanges
	{
		$changes = self::withoutId($changes);

		return new EntryChanges([...$changes->set, EntryFields::ID => Uuid::v7($this->clock->now())], $changes->remove, $changes->body);
	}

	/**
	 * Returns changes that leave the id alone: only the writer gives
	 * entries ids.
	 */
	private static function withoutId(EntryChanges $changes): EntryChanges
	{
		$set = $changes->set;

		unset($set[EntryFields::ID]);

		return new EntryChanges($set, array_values(array_diff($changes->remove, [EntryFields::ID])), $changes->body);
	}

	/**
	 * Returns whether a file's front matter has a valid id.
	 */
	private function hasId(string $path, string $contents): bool
	{
		try {
			return Uuid::isValid($this->parsers->parse($path, $contents)->frontMatter[EntryFields::ID] ?? null);
		} catch (InvalidDocument) {
			return false;
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
	 * Reindexes and moves the content version on, reading the files just
	 * written (by path) whatever their stat says.
	 *
	 * @param list<string> $written
	 */
	private function refresh(array $written = []): IndexReport
	{
		$report = $this->indexer->index(written: $written);
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
