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

use DateTimeInterface;
use Override;
use Blush\Content\FileNames;
use Blush\Content\Index\IndexFreshness;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Support\Slug;

/**
 * The filesystem driver's content writer (D-654): entries by id, each
 * found at its path in the index and written there by `FilesystemWriter`,
 * with what a write means for a file:
 *
 * - **A new slug** is written where the file says it: its `slug` key
 *   when it has one, else its name (D-277).
 * - **A new slug or publish date** renames a file its type names by date
 *   (D-519), and moves it to the folder its type's folder pattern gives
 *   (D-629): `FileNames::follow()`.
 * - **A tree's page** goes under another by moving into its folder, the
 *   parent made its folder's page when it's a file (D-408, D-410).
 */
final readonly class FilesystemContentWriter implements ContentWriter
{
	public function __construct(
		private FilesystemWriter $files,
		private IndexFreshness $freshness,
		private ContentTypes $types,
		private FileNames $fileNames
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(string $id): EditableEntry
	{
		return $this->files->load($this->path($id));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function loadAt(ContentType $type, string $key): ?EditableEntry
	{
		$path = $this->files->pathAt($type, $key);

		return $this->files->exists($path) ? $this->files->load($path) : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function create(ContentType $type, string $slug, EntryChanges $changes, ?string $parent = null, ?DateTimeInterface $date = null): string
	{
		if ($parent === null) {
			return $this->idAt($this->files->create($type, $slug, $changes, $date)->path);
		}

		$path = $this->path($parent);

		if ($this->record($path)?->type !== $type->name) {
			throw new WriteException(sprintf('The page to go under isn\'t one of the %s.', $type->labels->items));
		}

		return $this->idAt($this->files->createUnder($path, $slug, $changes)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createAt(ContentType $type, string $key, EntryChanges $changes): string
	{
		return $this->idAt($this->files->createAt($type, $key, $changes)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function duplicate(string $id, string $slug, EntryChanges $changes, ?DateTimeInterface $date = null): string
	{
		return $this->idAt($this->files->duplicate($this->path($id), $slug, $changes, $date)->path);
	}

	/**
	 * A new publish date renames and moves the file as its type's patterns
	 * name and place it.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function update(string $id, EntryChanges $changes, ?string $version = null): string
	{
		$path   = $this->path($id);
		$before = $this->record($path)?->published;
		$result = $this->files->update($path, $changes, $version);

		if ($this->record($result->path)?->published !== $before) {
			return $this->idAt($this->fileNames->follow($result->path) ?? $result->path);
		}

		return $this->idAt($result->path);
	}

	/**
	 * A file with a `slug` key (or one of its aliases) keeps its name and
	 * has the key changed; any other is renamed. Either then follows its
	 * type's patterns.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function rename(string $id, string $slug, ?string $version = null): string
	{
		$path  = $this->path($id);
		$entry = $this->record($path);

		if ($entry === null || ! $this->hasSlugKey($entry, $this->files->load($path)->frontMatter)) {
			return $this->followed($this->files->rename($path, $slug, $version)->path);
		}

		if (! Slug::isSlug($slug)) {
			throw new WriteException(sprintf('"%s" isn\'t a slug; try "%s".', $slug, Slug::from($slug)));
		}

		if ($entry->landing) {
			throw new WriteException(sprintf('%s is a landing page; its name is its folder\'s.', $path));
		}

		$folder = dirname($entry->key);
		$key    = ($folder === '.' ? '' : "{$folder}/") . $slug;
		$found  = $this->freshness->fresh()->snapshot()->find($entry->language, $entry->type, $key);

		if ($found !== null && $found !== $path) {
			throw new WriteException(sprintf('Another entry already has the slug "%s".', $slug));
		}

		return $this->followed($this->files->update($path, new EntryChanges(['slug' => $slug]), $version)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function move(string $id, ?string $parent, ?string $version = null): string
	{
		return $this->idAt($this->files->move($this->path($id), $parent === null ? null : $this->path($parent), $version)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function trash(string $id, ?string $version = null): string
	{
		return $this->idAt($this->files->trash($this->path($id), $version)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function restore(string $id, ?string $version = null, ?Status $status = Status::Draft): string
	{
		return $this->idAt($this->files->restore($this->path($id), $version, $status)->path);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $id, ?string $version = null): void
	{
		$this->files->delete($this->path($id), $version);
	}

	/**
	 * Returns whether a file names its entry's slug in its front matter.
	 *
	 * @param array<array-key, mixed> $frontMatter
	 */
	private function hasSlugKey(IndexRecord $entry, array $frontMatter): bool
	{
		$field = $this->types->find($entry->type) === null ? null : $this->types->schema($entry->type)->field('slug');

		return array_any($field === null ? ['slug'] : [$field->name, ...$field->aliases], static fn (string $key): bool => array_key_exists($key, $frontMatter));
	}

	/**
	 * Returns the path of the entry with an id: its own, or the steady one
	 * a file without one has as a record.
	 *
	 * @throws WriteException When there's none.
	 */
	private function path(string $id): string
	{
		return $this->freshness->fresh()->records()->path($id)
			?? throw new WriteException(sprintf('There\'s no entry with the id "%s".', $id));
	}

	/**
	 * Renames and moves a file its type's patterns name and place by its
	 * date or slug, returning the entry's id.
	 */
	private function followed(string $path): string
	{
		return $this->idAt($this->fileNames->follow($path) ?? $path);
	}

	/**
	 * Returns the index's record of the entry at a path.
	 */
	private function record(string $path): ?IndexRecord
	{
		return $this->freshness->fresh()->snapshot()->record($path);
	}

	/**
	 * Returns the id of the entry just written at a path.
	 *
	 * @throws WriteException When the index doesn't have it.
	 */
	private function idAt(string $path): string
	{
		return $this->record($path)->id ?? throw new WriteException(sprintf('%s was written, but the index doesn\'t have it; check Site Health.', $path));
	}
}
