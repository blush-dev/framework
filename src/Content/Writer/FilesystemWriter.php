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
 * - **Kept:** a deleted entry moves to `storage/trash/{time}/`.
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
	public function load(string $id): EditableEntry
	{
		$contents = $this->read($this->path($id));

		try {
			$document = $this->parsers->parse($id, $contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read: %s', $id, $e->getMessage()), previous: $e);
		}

		return new EditableEntry($id, $document->frontMatter, $document->body, self::revision($contents));
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
			$target = sprintf('%s/trash/%s/%s', $this->paths->storage, $this->clock->now()->format('Ymd-His'), $this->paths->relative($from));

			if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
				throw new WriteException(sprintf('The trash folder %s couldn\'t be created.', $this->paths->relative(dirname($target))));
			}

			if (! @rename($from, $target)) {
				throw new WriteException(sprintf('%s couldn\'t be moved to the trash.', $id));
			}

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
