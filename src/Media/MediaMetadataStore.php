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

use JsonException;
use Throwable;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;
use Blush\Field\Schema;
use Blush\Support\Filesystem;

/**
 * Reads and writes media files' metadata (D-238, D-269), never beside
 * the files (`media:publish` would serve it): a tree under
 * `user/data/media/` mirrors the media paths, one data file per media
 * file, and only for one that has something to say.
 * `user/media/2024/sunset.jpg` has `user/data/media/2024/sunset.jpg.json`
 * (D-631).
 *
 * Saving changes only the keys asked for (D-287), leaving any others as
 * they were written; a field is written
 * under whichever of its name and aliases the file already uses, and a
 * new key goes before the `id`, which stays last (D-487). A file
 * left with nothing in it is removed. A metadata file that can't be
 * read counts as none, so one bad file can't break the library;
 * `content:lint` is the place to hear about it.
 */
final readonly class MediaMetadataStore
{
	/**
	 * The folder under `user/data` metadata lives in.
	 */
	public const string FOLDER = 'media';

	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private DataLoader $data
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
			return MediaMetadata::fromArray($this->data->load($this->paths->data, $name) ?? []);
		} catch (DataException) {
			return new MediaMetadata();
		}
	}

	/**
	 * Every metadata file, found in one walk of `user/data/media`, by the
	 * media key it describes (`2024/sunset.jpg`): its path and modified
	 * time, for the media index and `content:lint`.
	 *
	 * @return array<string, array{path: string, modified: int}>
	 */
	public function files(): array
	{
		$found = [];

		foreach ($this->filesystem->files($this->paths->data . '/' . self::FOLDER) as $relative => $file) {
			if (DataLoader::isDataFile($file->getFilename())) {
				$key = substr(str_replace('\\', '/', (string) $relative), 0, -strlen($file->getExtension()) - 1);

				$found[$key] = ['path' => $file->getPathname(), 'modified' => (int) $file->getMTime()];
			}
		}

		ksort($found, SORT_STRING);

		return $found;
	}

	/**
	 * Returns whether a media path under `user/media` (such as
	 * `2024/sunset.jpg`) has a metadata file, as an upload that would
	 * take its name asks.
	 */
	public function has(string $relative): bool
	{
		try {
			return $this->data->find($this->paths->data, self::FOLDER . '/' . $relative) !== null;
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
	 * no metadata file, or one that reads as a map. One that doesn't is
	 * left for its author to fix (`content:lint` says why).
	 */
	public function isWritable(MediaFile $file): bool
	{
		$name = $this->name($file);

		if ($name === null) {
			return false;
		}

		try {
			$path = $this->data->find($this->paths->data, $name);

			if ($path === null) {
				return true;
			}

			$data = $this->data->loadFile($path);
		} catch (Throwable) {
			return false;
		}

		return $data === [] || ! array_is_list($data);
	}

	/**
	 * Saves changes to a media file's metadata: values to set (an empty
	 * one removes its key) and keys to remove, each under the name or
	 * alias the file already uses in `schema`.
	 *
	 * @param  array<string, mixed> $set
	 * @param  list<string>         $remove
	 * @throws MediaException When the file can't be written.
	 */
	public function save(MediaFile $file, array $set, array $remove = [], ?Schema $schema = null): void
	{
		$name = $this->name($file) ?? throw new MediaException(sprintf('"%s" isn\'t a media file metadata can be kept for.', $file->path));

		try {
			$path = $this->data->find($this->paths->data, $name);
		} catch (DataException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}

		$path ??= "{$this->paths->data}/{$name}.json";
		$text = is_file($path) ? (string) @file_get_contents($path) : '';

		// Each key's names: its field's name and aliases, when it has one.
		$changes = [];

		foreach ([...$set, ...array_fill_keys($remove, null)] as $key => $value) {
			$field     = $schema?->field((string) $key);
			$changes[] = [$field === null ? [(string) $key] : [$field->name, ...$field->aliases], $value === '' || $value === [] ? null : $value];
		}

		$next = self::json($text, $changes);

		if (trim($next) === '') {
			if (is_file($path) && ! @unlink($path)) {
				throw new MediaException(sprintf('Unable to remove "%s".', $path));
			}

			return;
		}

		$folder = dirname($path);

		if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
			throw new MediaException(sprintf('Unable to create "%s".', $folder));
		}

		try {
			$this->filesystem->writeAtomic($path, $next);
		} catch (Throwable $error) {
			throw new MediaException(sprintf('Unable to write "%s".', $path), previous: $error);
		}
	}

	/**
	 * Removes a media file's metadata (D-407), by the file's path under
	 * `user/media`.
	 *
	 * @throws MediaException When a file can't be removed.
	 */
	public function forget(string $relative): void
	{
		$name = self::FOLDER . '/' . trim($relative, '/');

		try {
			$path = $this->data->find($this->paths->data, $name);

			if ($path !== null && ! @unlink($path)) {
				throw new MediaException(sprintf('Unable to remove "%s".', $path));
			}
		} catch (DataException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}
	}

	/**
	 * The data file name for a media file (without `.json`), or `null` for a file outside the media folder.
	 */
	private function name(MediaFile $file): ?string
	{
		$path = $this->filesystem->normalize($file->path);

		$root = $this->filesystem->normalize($this->paths->media) . '/';

		return str_starts_with($path, $root) ? self::FOLDER . '/' . substr($path, strlen($root)) : null;
	}

	/**
	 * JSON with the changes made.
	 *
	 * @param  list<array{list<string>, mixed}> $changes
	 * @throws MediaException When the file isn't a JSON object.
	 */
	private static function json(string $text, array $changes): string
	{
		try {
			$data = trim($text) === '' ? [] : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new MediaException('The metadata file isn\'t valid JSON; fix it by hand first.', previous: $error);
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new MediaException('The metadata file isn\'t a JSON object; fix it by hand first.');
		}

		foreach ($changes as [$keys, $value]) {
			if ($value === null) {
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

		// A file with only an editor's schema pointer has nothing to say.
		return array_diff_key($data, [DataLoader::SCHEMA => true]) === [] ? '' : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}
}
