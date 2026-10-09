<?php

/**
 * Media metadata store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Container\Attributes\Defer;
use Blush\Core\Paths;
use Blush\Field\Schema;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;
use Blush\Support\Filesystem;
use Blush\Support\Uuid;

/**
 * Reads and writes media files' metadata (D-238, D-269), never beside
 * the files (`media:publish` would serve it): the `media` table (D-674,
 * D-675), a record for each media original that has something to say,
 * keyed by the file's id, with its `path` under `user/media` and its
 * `owner` declared, its description its content. On files, the records
 * are `user/data/media/{path}.json` (`MediaFiles`): `user/media/2024/sunset.jpg`
 * has `user/data/media/2024/sunset.jpg.json` (D-631).
 *
 * Saving changes only the keys asked for (D-287), leaving any others as
 * they were written; a field is written under whichever of its name and
 * aliases the record already uses, and a new key goes before the `id`,
 * which stays last (D-487). A record left with nothing in it is removed.
 * One without an id gets one when it's saved. A record that can't be
 * read counts as none, so one bad record can't break the library;
 * `content:lint` is the place to hear about it.
 */
final readonly class MediaMetadataStore
{
	/**
	 * The table's name.
	 */
	public const string TABLE = 'media';

	/**
	 * The folder under `user/data` the metadata files are in.
	 */
	public const string FOLDER = 'media';

	private Filesystem $filesystem;

	/**
	 * @param Closure(): MediaFiles $files
	 */
	public function __construct(
		private Paths $paths,
		private RecordStores|RecordStore $stores,
		private ClockInterface $clock,
		private StorageResolver $resolver,
		#[Defer(MediaFiles::class)] private Closure $files
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * The media table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, fields: ['path', 'owner']);
	}

	/**
	 * Returns a media file's metadata; empty when it has none.
	 */
	public function find(MediaFile $file): MediaMetadata
	{
		$path = $this->relative($file);

		if ($path === null) {
			return new MediaMetadata();
		}

		try {
			$record = $this->record($path);
		} catch (RecordException) {
			return new MediaMetadata();
		}

		return MediaMetadata::fromArray($record === null ? [] : self::data($record));
	}

	/**
	 * Every metadata record, by the media key it describes
	 * (`2024/sunset.jpg`): where it's kept and when it last changed (a
	 * stamp that changes when it does), for the media index and
	 * `content:lint`. On files, every metadata file, a record or not.
	 *
	 * @return array<string, array{location: string, modified: int}>
	 */
	public function files(): array
	{
		$found = [];

		if ($this->filesKept()) {
			foreach ($this->mediaFiles()->stamps() as $key => $modified) {
				$found[$key] = ['location' => $this->location($key), 'modified' => $modified];
			}

			return $found;
		}

		try {
			$records = $this->store()->select(self::table(), new RecordQuery()->withoutContent())->records;
		} catch (RecordException) {
			return [];
		}

		foreach ($records as $record) {
			$key = $record->fields['path'] ?? null;

			if (is_string($key)) {
				$found[$key] = ['location' => $this->location($key), 'modified' => crc32((string) $record->version)];
			}
		}

		ksort($found, SORT_STRING);

		return $found;
	}

	/**
	 * Reads a media key's metadata as it's kept, for checking it: on files
	 * the file as it is, a record or not. Empty when it has none.
	 *
	 * @return array<array-key, mixed>
	 * @throws RecordException When it can't be read.
	 */
	public function load(string $key): array
	{
		$key = trim($key, '/');

		if ($this->filesKept()) {
			return $this->mediaFiles()->raw($key) ?? [];
		}

		$record = $this->record($key);

		return $record === null ? [] : self::data($record);
	}

	/**
	 * Where a media key's metadata is kept, for people:
	 * `user/data/media/2024/sunset.jpg.json` for a file.
	 */
	public function location(string $key): string
	{
		$key = trim($key, '/');

		return $this->filesKept()
			? $this->paths->relative($this->mediaFiles()->root() . "/{$key}.json")
			: sprintf('%s/%s in the database', self::TABLE, $key);
	}

	/**
	 * Returns whether a media path under `user/media` (such as
	 * `2024/sunset.jpg`) has metadata, as an upload that would take its
	 * name asks. On files, a metadata file left behind counts.
	 */
	public function has(string $relative): bool
	{
		$relative = trim($relative, '/');

		if ($this->filesKept()) {
			return is_file($this->mediaFiles()->root() . "/{$relative}.json");
		}

		try {
			return $this->record($relative) !== null;
		} catch (RecordException) {
			return true;
		}
	}

	/**
	 * The first free path for a name in a folder under `user/media`: the
	 * name, else with `-2`, `-3`, … before its extension. A name with
	 * metadata left behind (its file was removed by hand) isn't free, so
	 * a new file doesn't take on another's alt text.
	 */
	public function freePath(string $folder, string $name): string
	{
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$base      = pathinfo($name, PATHINFO_FILENAME);
		$path      = "{$folder}/{$name}";
		$taken     = fn (string $path): bool => file_exists($path) || $this->has(substr($path, strlen($this->paths->media) + 1));

		for ($n = 2; $taken($path); $n++) {
			$path = "{$folder}/{$base}-{$n}" . ($extension === '' ? '' : ".{$extension}");
		}

		return $path;
	}

	/**
	 * Returns whether a media file's metadata can be written over: it has
	 * none, or what it has reads as a map. One that doesn't is left for
	 * its author to fix (`content:lint` says why).
	 */
	public function isWritable(MediaFile $file): bool
	{
		$path = $this->relative($file);

		if ($path === null) {
			return false;
		}

		try {
			$data = $this->load($path);
		} catch (RecordException) {
			return false;
		}

		return $data === [] || ! array_is_list($data);
	}

	/**
	 * Saves changes to a media file's metadata: values to set (an empty
	 * one removes its key) and keys to remove, each under the name or
	 * alias the record already uses in `schema`. The description is the
	 * record's content (`content`, D-674). A record without an id gets
	 * one.
	 *
	 * @param  array<string, mixed> $set
	 * @param  list<string>         $remove
	 * @throws MediaException When the record can't be read or written.
	 */
	public function save(MediaFile $file, array $set, array $remove = [], ?Schema $schema = null): void
	{
		$path = $this->relative($file) ?? throw new MediaException(sprintf('"%s" isn\'t a media file metadata can be kept for.', $file->path));

		try {
			$data   = $this->load($path);
			$record = $this->record($path);
		} catch (RecordException $error) {
			throw new MediaException(sprintf('The metadata can\'t be read; fix it by hand first. %s', $error->getMessage()), previous: $error);
		}

		if ($data !== [] && array_is_list($data)) {
			throw new MediaException('The metadata isn\'t a map of fields; fix it by hand first.');
		}

		// Each key's names: its field's name and aliases, when it has one.
		foreach ([...$set, ...array_fill_keys($remove, null)] as $key => $value) {
			$field = $schema?->field((string) $key);
			$keys  = $field === null ? [(string) $key] : [$field->name, ...$field->aliases];

			if ($value === '' || $value === [] || $value === null) {
				$data = array_diff_key($data, array_flip($keys));
			} else {
				$data[array_find($keys, static fn (string $key): bool => array_key_exists($key, $data)) ?? $keys[0]] = $value;
			}
		}

		try {
			if (array_diff_key($data, ['$schema' => true]) === []) {
				$this->forget($path);

				return;
			}

			// An id set or kept in the file wins over the record's (Media
			// IDs gives a file sharing one its own, D-487).
			$given   = $data[MediaMetadata::ID] ?? null;
			$data    = array_diff_key($data, [MediaMetadata::ID => true, '$schema' => true]);
			$id      = is_string($given) && Uuid::isValid($given) ? strtolower($given) : ($record->id ?? Uuid::v7($this->clock->now()));
			$content = $data[MediaMetadata::CONTENT] ?? null;
			$fields  = array_diff_key($data, [MediaMetadata::CONTENT => true, 'path' => true]);

			/** @var array<string, mixed> $fields */
			$this->store()->save(self::table(), new Record($id, [...$fields, 'path' => $path], is_string($content) ? $content : null));
		} catch (RecordException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Removes a media file's metadata (D-407), by the file's path under
	 * `user/media`; on files, a metadata file that isn't a record too.
	 *
	 * @throws MediaException When it can't be removed.
	 */
	public function forget(string $relative): void
	{
		$relative = trim($relative, '/');

		try {
			$record = $this->record($relative);

			if ($record !== null) {
				$this->store()->delete(self::table(), $record->id);
			}

			if ($this->filesKept()) {
				$this->mediaFiles()->discard($relative);
			}
		} catch (RecordException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * The record at a media path, or `null`.
	 *
	 * @throws RecordException
	 */
	private function record(string $path): ?Record
	{
		return $this->store()->select(self::table(), new RecordQuery()->where('path', Operator::Equal, trim($path, '/'))->limit(1))->records[0] ?? null;
	}

	/**
	 * A record's data as metadata: its fields without its path, its
	 * content as `content`, and its id, last.
	 *
	 * @return array<string, mixed>
	 */
	private static function data(Record $record): array
	{
		return [
			...array_diff_key($record->fields, ['path' => true]),
			...($record->content === null ? [] : [MediaMetadata::CONTENT => $record->content]),
			MediaMetadata::ID => $record->id
		];
	}

	/**
	 * A media file's path under `user/media`, or `null` for a file outside
	 * it.
	 */
	private function relative(MediaFile $file): ?string
	{
		$path = $this->filesystem->normalize($file->path);
		$root = $this->filesystem->normalize($this->paths->media) . '/';

		return str_starts_with($path, $root) ? substr($path, strlen($root)) : null;
	}

	/**
	 * Whether the metadata is kept as files.
	 */
	private function filesKept(): bool
	{
		return $this->resolver->covers(StorageArea::Data, MediaFiles::class);
	}

	/**
	 * The metadata files.
	 */
	private function mediaFiles(): MediaFiles
	{
		return ($this->files)();
	}

	/**
	 * The store that keeps the table.
	 *
	 * @throws RecordStoreFailure When the data area's driver keeps no records.
	 */
	private function store(): RecordStore
	{
		if ($this->stores instanceof RecordStore) {
			return $this->stores;
		}

		try {
			return $this->stores->store(self::table());
		} catch (StorageException $error) {
			throw new RecordStoreFailure($error->getMessage(), previous: $error);
		}
	}
}
