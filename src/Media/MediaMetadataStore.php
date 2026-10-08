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

use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataStore;
use Blush\Field\Schema;
use Blush\Support\Filesystem;

/**
 * Reads and writes media files' metadata (D-238, D-269), never beside
 * the files (`media:publish` would serve it): records in the data store
 * under `media/` mirror the media paths, one per media file, and only
 * for one that has something to say (D-642). For files,
 * `user/media/2024/sunset.jpg` has `user/data/media/2024/sunset.jpg.json`
 * (D-631).
 *
 * Saving changes only the keys asked for (D-287), leaving any others as
 * they were written; a field is written
 * under whichever of its name and aliases the record already uses, and a
 * new key goes before the `id`, which stays last (D-487). A record
 * left with nothing in it is removed. A record that can't be read
 * counts as none, so one bad record can't break the library;
 * `content:lint` is the place to hear about it.
 */
final readonly class MediaMetadataStore
{
	/**
	 * The folder in the data store metadata lives in.
	 */
	public const string FOLDER = 'media';

	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private DataStore $data
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * Returns a media file's metadata; empty when it has none.
	 */
	public function find(MediaFile $file): MediaMetadata
	{
		$name = $this->name($file);

		if ($name === null) {
			return new MediaMetadata();
		}

		try {
			return MediaMetadata::fromArray($this->data->load($name) ?? []);
		} catch (DataException) {
			return new MediaMetadata();
		}
	}

	/**
	 * Every metadata record, by the media key it describes
	 * (`2024/sunset.jpg`): where it's kept and when it last changed, for
	 * the media index and `content:lint`.
	 *
	 * @return array<string, array{location: string, modified: int}>
	 */
	public function files(): array
	{
		$found = [];

		foreach ($this->data->records(self::FOLDER) as $key => $modified) {
			$found[$key] = ['location' => $this->data->location(self::FOLDER . "/{$key}"), 'modified' => $modified];
		}

		return $found;
	}

	/**
	 * Reads a media key's metadata record as it's kept, for checking it.
	 * Empty when it has none.
	 *
	 * @return array<array-key, mixed>
	 * @throws DataException When it can't be read.
	 */
	public function load(string $key): array
	{
		return $this->data->load(self::FOLDER . '/' . trim($key, '/')) ?? [];
	}

	/**
	 * Returns whether a media path under `user/media` (such as
	 * `2024/sunset.jpg`) has metadata, as an upload that would take its
	 * name asks.
	 */
	public function has(string $relative): bool
	{
		try {
			return $this->data->has(self::FOLDER . '/' . trim($relative, '/'));
		} catch (DataException) {
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
	 * no record, or one that reads as a map. One that doesn't is left
	 * for its author to fix (`content:lint` says why).
	 */
	public function isWritable(MediaFile $file): bool
	{
		$name = $this->name($file);

		if ($name === null) {
			return false;
		}

		try {
			$data = $this->data->load($name) ?? [];
		} catch (DataException) {
			return false;
		}

		return $data === [] || ! array_is_list($data);
	}

	/**
	 * Saves changes to a media file's metadata: values to set (an empty
	 * one removes its key) and keys to remove, each under the name or
	 * alias the record already uses in `schema`.
	 *
	 * @param  array<string, mixed> $set
	 * @param  list<string>         $remove
	 * @throws MediaException When the record can't be read or written.
	 */
	public function save(MediaFile $file, array $set, array $remove = [], ?Schema $schema = null): void
	{
		$name = $this->name($file) ?? throw new MediaException(sprintf('"%s" isn\'t a media file metadata can be kept for.', $file->path));

		try {
			$data = $this->data->load($name) ?? [];
		} catch (DataException $error) {
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

		// The id stays last.
		if (array_key_exists(MediaMetadata::ID, $data)) {
			$id = $data[MediaMetadata::ID];
			unset($data[MediaMetadata::ID]);
			$data[MediaMetadata::ID] = $id;
		}

		try {
			if ($data === []) {
				$this->data->delete($name);
			} else {
				$this->data->save($name, $data);
			}
		} catch (DataException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Removes a media file's metadata (D-407), by the file's path under
	 * `user/media`.
	 *
	 * @throws MediaException When it can't be removed.
	 */
	public function forget(string $relative): void
	{
		try {
			$this->data->delete(self::FOLDER . '/' . trim($relative, '/'));
		} catch (DataException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * The record name for a media file, or `null` for a file outside the
	 * media folder.
	 */
	private function name(MediaFile $file): ?string
	{
		$path = $this->filesystem->normalize($file->path);

		$root = $this->filesystem->normalize($this->paths->media) . '/';

		return str_starts_with($path, $root) ? self::FOLDER . '/' . substr($path, strlen($root)) : null;
	}
}
