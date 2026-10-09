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
use Psr\Clock\ClockInterface;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\IndexFreshness;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\IndexReport;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\EntryFields;
use Blush\Content\Status;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Relation\LinkBuilder;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\Resolution;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Support\Slug;
use Blush\Support\Uuid;

/**
 * Writes content files in `user/content` (D-228), by path: the
 * filesystem driver's own writer, under its `ContentWriter`
 * (`FilesystemContentWriter`, which names entries by id) and the tools
 * only files have (`content:ids`, `content:refs`, file names and folders;
 * D-654):
 *
 * - **Confined:** every path is resolved inside the content folder, and
 *   only `.md` files are written (D-501), never anything executable
 *   (D-039).
 * - **Safe:** edits go through `DocumentEditor`, which keeps the file's
 *   formatting and refuses results that wouldn't read back as intended;
 *   files are written atomically, one write at a time (a lock file in
 *   `storage/cache`), and checked against the caller's version (a hash of the file) first.
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
 * - **Linked:** each entry written has both forms of its relations filed
 *   (D-589, D-596): its written values and their ids under `refs`. Before
 *   an entry is renamed or moved (with the pages under it), the entries
 *   linking to it have their ids filed; after, their written values are
 *   rewritten from the ids, so their links follow it.
 *
 * Front matter keys go through the entry's type schema, so setting
 * `published` updates a file's `date` alias in place.
 */
final readonly class FilesystemWriter
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private DocumentParser $parser,
		private DocumentEditor $editor,
		private ContentTypes $types,
		private IndexFreshness $freshness,
		private Indexer $indexer,
		private ContentIndex $index,
		private Relations $relations,
		private ContentVersion $contentVersion,
		private ClockInterface $clock,
		private AppConfig $app
	) {}

	/**
	 * Reads an entry's file for editing: its front matter as written, its
	 * body, and its version.
	 *
	 * @throws WriteException When there's no such file or it can't be read.
	 */
	public function load(string $path): EditableEntry
	{
		$file     = $this->file($path);
		$contents = $this->read($file);

		try {
			$document = $this->parser->parse($contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read: %s', $path, $e->getMessage()), previous: $e);
		}

		clearstatcache(true, $file);
		$modified = @filemtime($file);

		$id = $document->frontMatter[EntryFields::ID] ?? null;

		return new EditableEntry(is_string($id) && Uuid::isValid($id) ? strtolower($id) : '', $document->frontMatter, $document->body, self::version($contents), $modified === false ? null : $modified, $path);
	}

	/**
	 * Returns whether there's a content file at a path.
	 *
	 * @throws WriteException When the path isn't a content file's.
	 */
	public function exists(string $path): bool
	{
		return is_file($this->file($path));
	}

	/**
	 * Creates an entry of a type from a slug, named by the type's
	 * pattern (`ContentType::naming()`, D-511): `{folder}/{slug}.md`
	 * without one of its own (D-515), in the folders its folder pattern
	 * gives (`ContentType::directoryFor()`, D-629). A pattern's date
	 * defaults to now.
	 *
	 * @throws WriteException When the file exists or the slug is invalid.
	 */
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$date ??= $this->clock->now();
		$name   = $type->naming()->name($slug, $date) . '.' . FilesystemSource::EXTENSION;
		$path   = ltrim($type->directoryFor($slug, $date) . "/{$name}", '/');
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $type, $changes): WriteResult {
			if (file_exists($file)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($file)));
			}

			$this->write($file, $this->editor->edit($path, '', $this->withNew($changes), $this->keys($type->name)));

			$report = $this->refresh([$path]);

			return new WriteResult($path, $this->versionOf($path), $report);
		});
	}

	/**
	 * Creates a page a type keeps at a fixed key in its folder, undated:
	 * `{folder}/{key}.md`.
	 *
	 * @throws WriteException When the file exists or the key is invalid.
	 */
	public function createAt(ContentType $type, string $key, EntryChanges $changes): WriteResult
	{
		$path = $this->pathAt($type, $key);
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $type, $changes): WriteResult {
			if (file_exists($file)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($file)));
			}

			$this->write($file, $this->editor->edit($path, '', $this->withNew($changes), $this->keys($type->name)));

			$report = $this->refresh([$path]);

			return new WriteResult($path, $this->versionOf($path), $report);
		});
	}

	/**
	 * Returns whether a key is one a page can be written at: slugs
	 * separated by `/`, each of which may start with one `_`.
	 */
	public static function isPageKey(string $key): bool
	{
		return array_all(explode('/', $key), static fn (string $segment): bool => Slug::isSlug(ltrim($segment, '_')) && strlen(ltrim($segment, '_')) >= strlen($segment) - 1);
	}

	/**
	 * Returns the path of the page a type keeps at a fixed key, as
	 * `createAt()` writes it, whether or not it exists yet. Each of the
	 * key's segments is a slug, and may start with `_` to keep it out of
	 * listings, such as `_cooks` or `_cooks/jane` (D-602).
	 *
	 * @throws WriteException When the key is invalid.
	 */
	public function pathAt(ContentType $type, string $key): string
	{
		if (! self::isPageKey($key)) {
			throw new WriteException(sprintf('"%s" isn\'t a page key: slugs separated by "/", each may start with "_".', $key));
		}

		return ltrim("{$type->folder}/{$key}." . FilesystemSource::EXTENSION, '/');
	}

	/**
	 * Creates a page of a tree under another (D-408), undated:
	 * `{folder}/{parent key}/{slug}.md`, so its key is the parent's
	 * and its slug. A parent kept as a file named for its key
	 * (`about.md`) becomes its folder's page first (`about/index.md`), so
	 * a page and its children share a folder; its key and address stay
	 * the same, and the result's `moved` names its new path. A parent
	 * that's already a folder's page, or whose file name says more than
	 * its key (an order prefix, a `slug:` of its own), stays where it is.
	 *
	 * @throws WriteException When the parent isn't a page of a tree, its
	 *                        folder already has a page, the page exists,
	 *                        or the slug is invalid.
	 */
	public function createUnder(string $parentPath, string $slug, EntryChanges $changes): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		return $this->locked(function () use ($parentPath, $slug, $changes): WriteResult {
			$parent = $this->record($parentPath);
			$type   = $parent === null ? null : $this->types->find($parent->type);

			if ($parent === null || ! $type instanceof Tree) {
				throw new WriteException(sprintf('%s isn\'t a page other pages can go under.', $parentPath));
			}

			if ($parent->landing) {
				throw new WriteException(sprintf('%s is an index page; pages under it go at the top.', $parentPath));
			}

			$folder = ltrim("{$type->folder}/{$parent->key}", '/');
			$path   = "{$folder}/" . $type->naming()->name($slug, $this->clock->now()) . '.' . FilesystemSource::EXTENSION;
			$file   = $this->file($path);

			if (
				$this->named($type->name, "{$parent->key}/{$slug}") !== null
				|| file_exists($file)
				|| file_exists($this->folder("{$folder}/{$slug}") . '/index.' . FilesystemSource::EXTENSION)
			) {
				throw new WriteException(sprintf('There\'s already a page at %s/%s.', $parent->key, $slug));
			}

			// Edited before anything moves, so a refused edit moves nothing.
			$contents = $this->editor->edit($path, '', $this->withNew($changes), $this->keys($type->name));
			$moved    = [];
			$made     = ! is_dir($this->folder($folder));

			if ($parent->path === "{$folder}." . FilesystemSource::EXTENSION) {
				$moved = [$parent->path => $this->promote($parent->path, $folder)];
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

			$report = $this->refresh([$path]);

			return new WriteResult($path, $this->versionOf($path), $report, $moved);
		});
	}

	/**
	 * Makes a page kept as a file its folder's page (D-408): `about.md`
	 * becomes `about/index.md`, the folder made if it isn't there.
	 * Returns its new path.
	 *
	 * @throws WriteException When the folder already has a page.
	 */
	private function promote(string $path, string $folder): string
	{
		$directory = $this->folder($folder);

		if (file_exists("{$directory}/index." . FilesystemSource::EXTENSION)) {
			throw new WriteException(sprintf('%s can\'t move into %s/: that folder already has a page. Remove one of the two, then try again.', $path, $folder));
		}

		$made = ! is_dir($directory);

		if ($made && ! @mkdir($directory, 0775, true)) {
			throw new WriteException(sprintf('The folder %s couldn\'t be created.', $this->paths->relative($directory)));
		}

		$newPath = "{$folder}/index." . FilesystemSource::EXTENSION;

		if (! @rename($this->file($path), $this->file($newPath))) {
			if ($made) {
				@rmdir($directory);
			}

			throw new WriteException(sprintf('%s couldn\'t be moved to %s.', $path, $newPath));
		}

		return $newPath;
	}

	/**
	 * Copies an entry beside it (D-275) under a new slug, with changes
	 * applied to the copy: `{slug}`, or the first of `{slug}-2`,
	 * `{slug}-3`, … that's free. The copy is named as its type names new
	 * files (D-511), with the date given (default now). A bundle's copy is a copy of its
	 * folder. A landing page can't be copied; its name is its folder's.
	 *
	 * @throws WriteException When the entry can't be read or the copy written.
	 */
	public function duplicate(string $path, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $slug, $changes, $date): WriteResult {
			$contents = $this->read($file);
			$entry    = $this->record($path);

			if ($entry?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s, so it can\'t be copied.', $path));
			}

			// A copy is a new file, so it's named as its type names them now,
			// and a collection's is a file in the folder its pattern gives
			// (D-514, D-629).
			$type   = ($entry === null ? null : $this->types->find($entry->type)) ?? $this->types->forFile($path);
			$date ??= $this->clock->now();
			$prefix = $type->naming()->prefix($date);
			$number = 1;

			do {
				$copy = $number === 1 ? $slug : "{$slug}-{$number}";
				[$from, $to, $newPath, $bundle] = $this->duplicateTargets($path, $copy, $prefix, $type->keysByFolder() ? null : $type->directoryFor($copy, $date, $type->hiddenFolders($path)));
				$number++;
			} while (file_exists($to));

			if ($bundle) {
				$this->copyFolder($from, $to);
			}

			$this->write($this->file($newPath), $this->editor->edit($newPath, $contents, $this->withNew($changes, $this->frontMatter($contents)), $this->keys($entry?->type)));

			$report = $this->refresh([$newPath]);

			return new WriteResult($newPath, $this->versionOf($newPath), $report);
		});
	}

	/**
	 * Changes an entry's front matter and body. A file without an id is
	 * given one.
	 *
	 * @throws WriteException
	 */
	public function update(string $path, EntryChanges $changes, ?string $version = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $changes, $version): WriteResult {
			$contents = $this->current($path, $file, $version);

			if ($changes->isEmpty()) {
				return new WriteResult($path, self::version($contents), new IndexReport());
			}

			// The id isn't changed by an edit, but one is given to a file without it.
			$changes = $this->hasId($path, $contents) ? self::withoutId($changes) : $this->withId($changes);
			$edited  = $this->editor->edit($path, $contents, $changes, $this->keys($this->record($path)?->type));

			if ($edited !== $contents) {
				$this->write($file, $edited);
			}

			if ($edited === $contents) {
				return new WriteResult($path, self::version($edited), new IndexReport());
			}

			$report = $this->refresh([$path]);

			return new WriteResult($path, $this->versionOf($path), $report);
		});
	}

	/**
	 * Gives an entry's file a new slug: it's renamed (keeping any prefix,
	 * whatever pattern named it, and a language suffix; D-511), or its
	 * folder for a bundle (`{slug}/index.md`).
	 *
	 * @throws WriteConflict
	 * @throws WriteException When the new name is taken or invalid.
	 */
	public function rename(string $path, string $slug, ?string $version = null): WriteResult
	{
		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $slug, $version): WriteResult {
			$contents = $this->current($path, $file, $version);

			$entry = $this->record($path);

			if ($entry?->landing === true) {
				throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s.', $path));
			}

			[$from, $to, $newPath] = $this->renameTargets($path, $slug, $entry);

			if ($from === $to) {
				return new WriteResult($path, self::version($contents), new IndexReport());
			}

			if (file_exists($to)) {
				throw new WriteException(sprintf('%s already exists.', $this->paths->relative($to)));
			}

			[$referrers, $filed] = $this->fileReferrers($path);

			if (! @rename($from, $to)) {
				throw new WriteException(sprintf('%s couldn\'t be renamed.', $path));
			}

			$report = $this->refresh($filed, $referrers);

			return new WriteResult($newPath, $this->versionOf($newPath), $report);
		});
	}

	/**
	 * Moves a tree's page under another of its pages, or to the top with
	 * `null` (D-410), keeping its slug: its file (or its folder, for one
	 * kept as `{slug}/index.md`) moves into the new parent's folder, with
	 * the folder of pages under it, so they come along. A new parent kept
	 * as a file named for its key becomes its folder's page first, as in
	 * `createUnder()`. Moving to where it already is changes nothing. The
	 * result's `moved` names every entry that moved, old path to new.
	 *
	 * @throws WriteException When it isn't a tree's page, the parent isn't
	 *                        one of its pages (or is the page itself, or
	 *                        under it), or a page is already there.
	 */
	public function move(string $path, ?string $parentPath, ?string $version = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $parentPath, $version): WriteResult {
			$contents = $this->current($path, $file, $version);
			$entry    = $this->record($path);
			$type     = $entry === null ? null : $this->types->find($entry->type);

			if ($entry === null || ! $type instanceof Tree) {
				throw new WriteException(sprintf('%s isn\'t a page that can move.', $path));
			}

			if ($entry->landing) {
				throw new WriteException(sprintf('%s is an index page; it stays at the top of its folder.', $path));
			}

			$parent = $parentPath === null ? null : $this->record($parentPath);

			if ($parentPath !== null && ($parent === null || $parent->type !== $type->name || $parent->landing)) {
				throw new WriteException(sprintf('%s isn\'t one of the %s a page can go under.', $parentPath, $type->labels->items));
			}

			if ($parent !== null && ($parent->key === $entry->key || str_starts_with($parent->key, "{$entry->key}/"))) {
				throw new WriteException(sprintf('%s can\'t go under itself or a page under it.', $entry->title === '' ? $path : $entry->title));
			}

			if ($type->parentKey($entry->key, []) === $parent?->key) {
				return new WriteResult($path, self::version($contents), new IndexReport());
			}

			$slug   = basename($entry->key);
			$newKey = $parent === null ? $slug : "{$parent->key}/{$slug}";
			$target = ltrim($type->folder . ($parent === null ? '' : "/{$parent->key}"), '/');

			if ($this->named($type->name, $newKey) !== null) {
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
					if (FilesystemSource::isContent($relative)) {
						$moved["{$from}/{$relative}"] = "{$to}/{$relative}";
					}
				}
			}

			ksort($moved);

			[$referrers, $filed] = $this->fileReferrers($path);

			$done = [];
			$made = ! is_dir($this->folder($target));

			try {
				if ($parent !== null && $parent->path === "{$target}." . FilesystemSource::EXTENSION) {
					$promoted = $this->promote($parent->path, $target);
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

			$report = $this->refresh($filed, $referrers);

			return new WriteResult($newPath, $this->versionOf($newPath), $report, $moved);
		});
	}

	/**
	 * Moves an entry to the trash (D-484): it stays where it is, with
	 * `status: trash` and `trashed` (when) in its front matter.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	public function trash(string $path, ?string $version = null): WriteResult
	{
		return $this->update($path, new EntryChanges([
			'status'              => Status::Trash->value,
			EntryFields::TRASHED => $this->clock->now()->format('Y-m-d H:i:s P')
		]), $version);
	}

	/**
	 * Brings an entry back from the trash (D-484): its `status` becomes
	 * `$status`, or goes when that's `null`, and its `trashed` date goes.
	 *
	 * @throws WriteException
	 */
	public function restore(string $path, ?string $version = null, ?Status $status = Status::Draft): WriteResult
	{
		return $this->update($path, $status === null
			? new EntryChanges([], ['status', EntryFields::TRASHED])
			: new EntryChanges(['status' => $status->value], [EntryFields::TRASHED]), $version);
	}

	/**
	 * Deletes an entry for good: its file, and its bundle's folder when
	 * nothing else is left in it.
	 *
	 * @throws WriteException
	 */
	public function delete(string $path, ?string $version = null): WriteResult
	{
		$file = $this->file($path);

		return $this->locked(function () use ($path, $file, $version): WriteResult {
			$this->current($path, $file, $version);

			$bundle = preg_match('#(^|/)index\.[a-z]+$#', $path) === 1 && $this->record($path)?->landing !== true;

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
	private function renameTargets(string $path, string $slug, ?IndexRecord $entry): array
	{
		$directory = dirname($path) === '.' ? '' : dirname($path);
		$file      = basename($path);

		if (preg_match('/^index\.([a-z]+)$/', $file) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';
			$newPath  = "{$parent}{$slug}/{$file}";

			return [$this->folder($directory), $this->folder("{$parent}{$slug}"), $newPath];
		}

		// What's around the slug stays: a prefix, whatever pattern named
		// the file (D-511), and a language suffix.
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		$parts     = explode('.', pathinfo($file, PATHINFO_FILENAME));
		$suffix    = count($parts) > 1 && $entry !== null && end($parts) === $entry->language && end($parts) !== $entry->slug ? '.' . array_pop($parts) : '';

		array_pop($parts);

		$newPath = ltrim("{$directory}/" . implode('', array_map(static fn (string $part): string => "{$part}.", $parts)) . "{$slug}{$suffix}." . ($extension === '' ? 'md' : $extension), '/');

		return [$this->file($path), $this->file($newPath), $newPath];
	}

	/**
	 * Returns what a copy copies (the file, or a bundle's folder), where
	 * to, the copy's path, and whether it's a bundle. A file's copy is
	 * named `$prefix` and its slug (D-511); a bundle's folder is named by
	 * the slug alone, since a file name pattern never names folders
	 * (D-513). A collection's or the profiles' copy is a file in
	 * `$placed`, the folder its folder pattern gives it (D-514, D-629),
	 * a bundle's included.
	 *
	 * @return array{string, string, string, bool}
	 * @throws WriteException
	 */
	private function duplicateTargets(string $path, string $slug, string $prefix, ?string $placed): array
	{
		$directory = dirname($path) === '.' ? '' : dirname($path);
		$file      = basename($path);

		if (preg_match('/^index\.([a-z]+)$/', $file, $index) === 1 && $directory !== '') {
			$parent = dirname($directory) === '.' ? '' : dirname($directory) . '/';

			if ($placed !== null) {
				$newPath = ltrim("{$placed}/{$prefix}{$slug}.{$index[1]}", '/');

				return [$this->file($path), $this->file($newPath), $newPath, false];
			}

			return [$this->folder($directory), $this->folder($parent . $slug), "{$parent}{$slug}/{$file}", true];
		}

		preg_match('/^.+\.([a-z]+)$/', $file, $match);

		$newPath = ltrim(($placed ?? $directory) . "/{$prefix}{$slug}." . ($match[1] ?? 'md'), '/');

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
	 * Returns a file's contents after checking the version.
	 *
	 * @throws WriteConflict
	 * @throws WriteException
	 */
	private function current(string $path, string $file, ?string $version): string
	{
		$contents = $this->read($file);

		if ($version !== null && ! hash_equals(self::version($contents), $version)) {
			throw new WriteConflict(sprintf('%s changed since it was opened. Reload it, then make your change again.', $path));
		}

		return $contents;
	}

	/**
	 * Returns the absolute path for an entry's path, confined to the content folder
	 * and to `.md` files.
	 *
	 * @throws WriteException
	 */
	private function file(string $path): string
	{
		if ($path === '' || str_starts_with($path, '/') || ! FilesystemSource::isContent($path) || str_contains($path, "\0")) {
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
	 * Gives entries new ids (D-477), for `content:ids` and Site Health:
	 * each file's `id` is set to a new UUIDv7 (added last when it has
	 * none), all in one reindex. A file that can't be read or changed is
	 * left as it is and named in the result.
	 *
	 * @param list<string> $paths
	 */
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

					$this->write($file, $this->editor->edit($path, $contents, $changes, $this->keys($this->record($path)?->type)));

					$ids[$path] = $id;
				} catch (WriteException $e) {
					$failed[$path] = $e->getMessage();
				}
			}

			return new AssignedIds($ids, $failed, $ids === [] ? new IndexReport() : $this->refresh(array_keys($ids)));
		});
	}

	/**
	 * Renames entries' files and folders (D-512), for `content:filenames`,
	 * folders (D-629), and Site Health: each entry, by its path, moves a
	 * set of files or folders (paths in the content folder, old to new)
	 * together, so an entry and its translations move as one, all in one
	 * reindex. When a move can't be made (a new path is taken, an old one
	 * is gone), the entry's earlier moves are undone, and it's named in
	 * the result. A folder a move leaves empty is removed (D-514).
	 *
	 * @param array<string, array<string, string>> $moves Old paths to new, by entry path; the entry's own file comes first.
	 */
	public function renameFiles(array $moves): RenamedFiles
	{
		return $this->locked(function () use ($moves): RenamedFiles {
			$renamed = [];
			$failed  = [];

			foreach ($moves as $path => $entryMoves) {
				$done = [];

				try {
					foreach ($entryMoves as $from => $to) {
						$this->moveItem($from, $to);
						$done[$from] = $to;
					}

					$renamed[$path] = self::moved($path, $done);

					foreach (array_keys($done) as $from) {
						$this->removeEmptyFolders(dirname($from));
					}
				} catch (WriteException $e) {
					foreach (array_reverse($done, true) as $from => $to) {
						@rename($this->folder($to), $this->folder($from));
						$this->removeEmptyFolders(dirname($to));
					}

					$failed[$path] = $e->getMessage();
				}
			}

			return new RenamedFiles($renamed, $failed, $renamed === [] ? new IndexReport() : $this->refresh());
		});
	}

	/**
	 * Removes a folder in the content folder when a move left it empty,
	 * and each folder above it that's then empty too.
	 */
	private function removeEmptyFolders(string $relative): void
	{
		while ($relative !== '' && $relative !== '.') {
			$folder = $this->folder($relative);

			if (! is_dir($folder) || new FilesystemIterator($folder)->valid() || ! @rmdir($folder)) {
				return;
			}

			$relative = dirname($relative);
		}
	}

	/**
	 * Returns where a path is after moves: its own, or its folder's.
	 *
	 * @param array<string, string> $moves Old paths to new.
	 */
	private static function moved(string $path, array $moves): string
	{
		foreach ($moves as $from => $to) {
			if ($path === $from || str_starts_with($path, "{$from}/")) {
				return $to . substr($path, strlen($from));
			}
		}

		return $path;
	}

	/**
	 * Moves a content file, or a folder in the content folder, to a path
	 * that's free.
	 *
	 * @throws WriteException
	 */
	private function moveItem(string $from, string $to): void
	{
		$source = $this->folder($from);
		$target = is_dir($source) ? $this->folder($to) : $this->file($to);

		if (! is_dir($source)) {
			$source = $this->file($from);
		}

		if (! file_exists($source)) {
			throw new WriteException(sprintf('There\'s no %s.', $from));
		}

		if (file_exists($target)) {
			throw new WriteException(sprintf('%s already exists.', $to));
		}

		// A folder pattern's folder may not be there yet (D-629).
		if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
			throw new WriteException(sprintf('The folder for %s couldn\'t be created.', $to));
		}

		if (! @rename($source, $target)) {
			throw new WriteException(sprintf('%s couldn\'t be renamed.', $from));
		}
	}

	/**
	 * Returns changes for a new file: its id (one the changes give, when
	 * it's a UUID no entry has, as a record saved with its id, D-653;
	 * else a new one), and a `published` date
	 * of now in the site's time zone when neither the changes nor the
	 * front matter it starts from (a copy's) has one (every kind, D-514).
	 *
	 * @param array<array-key, mixed> $frontMatter
	 */
	private function withNew(EntryChanges $changes, array $frontMatter = []): EntryChanges
	{
		$given   = $changes->set[EntryFields::ID] ?? null;
		$changes = $this->withId($changes, is_string($given) && Uuid::isValid($given) && $this->index->snapshot()->path(strtolower($given)) === null ? strtolower($given) : null);

		if (array_intersect_key([...$frontMatter, ...$changes->set], ['published' => true, 'date' => true]) !== []) {
			return $changes;
		}

		$published = $this->clock->now()->setTimezone($this->app->timezone())->format('Y-m-d H:i:s P');
		$set       = $changes->set;
		$id        = $set[EntryFields::ID];

		unset($set[EntryFields::ID]);

		return new EntryChanges([...$set, 'published' => $published, EntryFields::ID => $id], $changes->remove, $changes->body);
	}

	/**
	 * Returns changes with a new id (D-477) set last, so a file that has
	 * none gets it at the end of its front matter, and one that has one
	 * (a copy) gets it in its place.
	 */
	private function withId(EntryChanges $changes, ?string $id = null): EntryChanges
	{
		$changes = self::withoutId($changes);

		return new EntryChanges([...$changes->set, EntryFields::ID => $id ?? Uuid::v7($this->clock->now())], $changes->remove, $changes->body);
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
	 * Returns a file's front matter, or none when it can't be parsed.
	 *
	 * @return array<array-key, mixed>
	 */
	private function frontMatter(string $contents): array
	{
		try {
			return $this->parser->parse($contents)->frontMatter;
		} catch (InvalidDocument) {
			return [];
		}
	}

	/**
	 * Returns whether a file's front matter has a valid id.
	 */
	private function hasId(string $path, string $contents): bool
	{
		try {
			return Uuid::isValid($this->parser->parse($contents)->frontMatter[EntryFields::ID] ?? null);
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
	 * Files both forms of entries' relations (D-589, D-596), for
	 * `content:refs` and Site Health: each file whose written values or
	 * `refs` differ from what its links say is rewritten, all in one
	 * reindex. A file that can't be read or changed is left as it is and
	 * named in the result.
	 *
	 * @param list<string> $paths
	 */
	public function fileRefs(array $paths): FiledRefs
	{
		return $this->locked(function () use ($paths): FiledRefs {
			$this->indexer->index();

			$snapshot = $this->index->snapshot();
			$stale    = array_intersect_key(new LinkBuilder()->build($snapshot, $this->relations, $this->types)->stale, array_flip($paths));

			[$filed, $failed] = $this->fileForms($stale, $snapshot);

			return new FiledRefs($filed, $failed, $filed === [] ? new IndexReport() : $this->refresh($filed));
		});
	}

	/**
	 * Reindexes and moves the content version on, reading the files just
	 * written (by path) whatever their stat says. The relations of those
	 * files, and of the entries `$referrers` names (by id), are filed
	 * where Blush would file them differently (D-596), and reindexed.
	 *
	 * @param list<string> $written
	 * @param list<string> $referrers
	 */
	private function refresh(array $written = [], array $referrers = []): IndexReport
	{
		$report   = $this->indexer->index(written: $written);
		$snapshot = $this->index->snapshot();
		$paths    = [...$written, ...array_filter(array_map($snapshot->path(...), $referrers))];
		$stale    = array_intersect_key($report->stale, array_flip($paths));

		if ($stale !== []) {
			[$filed] = $this->fileForms($stale, $snapshot);

			if ($filed !== []) {
				$again  = $this->indexer->index(written: $filed);
				$report = new IndexReport(
					$again->total,
					$report->added,
					array_values(array_diff(array_unique([...$report->changed, ...$again->changed]), $report->added)),
					$report->removed,
					[...$report->failures, ...$again->failures],
					$report->full,
					true,
					$again->stale
				);
			}
		}

		$this->contentVersion->bump();

		return $report;
	}

	/**
	 * Files the ids of the entries linking to an entry, and to the pages
	 * under it in a tree, before it's renamed or moved (D-596), so their
	 * links can follow it. Returns those entries' ids and the paths
	 * written.
	 *
	 * @return array{list<string>, list<string>}
	 */
	private function fileReferrers(string $path): array
	{
		$snapshot = $this->index->snapshot();
		$record   = $snapshot->record($path);

		if ($record?->id === null) {
			return [[], []];
		}

		$ids = [$record->id];

		if ($this->types->find($record->type) instanceof Tree) {
			foreach ($snapshot->records as $other) {
				if ($other['id'] !== null && $other['type'] === $record->type && str_starts_with($other['key'], "{$record->key}/")) {
					$ids[] = $other['id'];
				}
			}
		}

		$graph     = $snapshot->graph();
		$referrers = array_values(array_unique(array_merge(...array_map($graph->sources(...), $ids))));

		if ($referrers === []) {
			return [[], []];
		}

		$paths = array_filter(array_map($snapshot->path(...), $referrers));
		$stale = array_intersect_key(new LinkBuilder()->build($snapshot, $this->relations, $this->types)->stale, array_flip($paths));

		return [$referrers, $this->fileForms($stale, $snapshot)[0]];
	}

	/**
	 * Files both forms of entries' relations (D-589): each file's
	 * resolutions written as `RelationForms` gives them. A file that
	 * can't be read or changed is left as it is.
	 *
	 * @param  array<string, array<string, Resolution>> $stale Resolutions by path and relation name.
	 * @return array{list<string>, array<string, string>} The paths written, and messages for those that failed.
	 */
	private function fileForms(array $stale, IndexSnapshot $snapshot): array
	{
		$filed  = [];
		$failed = [];

		foreach ($stale as $path => $resolutions) {
			$path = (string) $path;
			$type = $snapshot->record($path)?->type;

			if ($type === null) {
				continue;
			}

			try {
				$file     = $this->file($path);
				$contents = $this->read($file);
				$changes  = RelationForms::changes($this->frontMatter($contents), $resolutions, $this->relations, $type, $this->types->find($type) instanceof Tree);

				if ($changes->isEmpty()) {
					continue;
				}

				$this->write($file, $this->editor->edit($path, $contents, $changes, $this->keys($type)));

				$filed[] = $path;
			} catch (WriteException $e) {
				$failed[$path] = $e->getMessage();
			}
		}

		return [$filed, $failed];
	}

	/**
	 * Returns the index's record of the entry at a path, the index brought
	 * up to date once a request.
	 */
	private function record(string $path): ?IndexRecord
	{
		return $this->freshness->fresh()->snapshot()->record($path);
	}

	/**
	 * Returns the path of a type's entry with a key in the site's default
	 * language, or `null`.
	 */
	private function named(string $type, string $key): ?string
	{
		return $this->freshness->fresh()->snapshot()->find($this->app->languages->default->code, $type, $key);
	}

	/**
	 * Returns a file's version as it is now.
	 *
	 * @throws WriteException When it can't be read.
	 */
	private function versionOf(string $path): string
	{
		return self::version($this->read($this->file($path)));
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
	 * Returns a file's version (D-648): a hash of its contents.
	 */
	private static function version(string $contents): string
	{
		return RecordBuilder::hash($contents);
	}
}
