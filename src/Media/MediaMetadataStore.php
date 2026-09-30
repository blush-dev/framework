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
use Blush\Content\Writer\YamlMap;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;
use Blush\Support\Filesystem;

/**
 * Reads and writes media files' metadata (D-238, D-269), never beside
 * the files (`media:publish` would serve it): a tree under
 * `user/data/media/` mirrors the media paths, one data file per media
 * file, and only for one that has something to say.
 * `user/media/2024/sunset.jpg` has `user/data/media/2024/sunset.jpg.yml`
 * (or `.yaml` or `.json`, read in the data loader's order); a page
 * bundle's file is under `_content/`, as it's served
 * (`user/content/posts/hello/photo.jpg` →
 * `user/data/media/_content/posts/hello/photo.jpg.yml`).
 *
 * Saving changes only `alt` and `caption`, leaving any other keys, and
 * the rest of a YAML file, as they were written; a file left with
 * nothing in it is removed. A new file is YAML. A metadata file that
 * can't be read counts as none, so one bad file can't break the library;
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
	 * Saves a media file's metadata.
	 *
	 * @throws MediaException When the file can't be written.
	 */
	public function save(MediaFile $file, MediaMetadata $metadata): void
	{
		$name = $this->name($file) ?? throw new MediaException(sprintf('"%s" isn\'t a media file metadata can be kept for.', $file->path));

		try {
			$path = $this->data->find($this->paths->data, $name);
		} catch (DataException $error) {
			throw new MediaException($error->getMessage(), previous: $error);
		}

		$path ??= "{$this->paths->data}/{$name}.yml";
		$text = is_file($path) ? (string) @file_get_contents($path) : '';

		$next = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json'
			? self::json($text, $metadata)
			: self::yaml($text, $metadata);

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
	 * The data file name for a media file (without the data format's
	 * extension), or `null` for a file outside the media and content
	 * folders.
	 */
	private function name(MediaFile $file): ?string
	{
		$path = $this->filesystem->normalize($file->path);

		foreach ([$this->paths->media => '', $this->paths->content => MediaResolver::CONTENT . '/'] as $root => $prefix) {
			$root = $this->filesystem->normalize($root) . '/';

			if (str_starts_with($path, $root)) {
				return self::FOLDER . '/' . $prefix . substr($path, strlen($root));
			}
		}

		return null;
	}

	/**
	 * YAML with `alt` and `caption` set, or removed when empty.
	 */
	private static function yaml(string $text, MediaMetadata $metadata): string
	{
		$map = YamlMap::fromText($text);

		foreach ($metadata->toArray() as $key => $value) {
			$map = $value === '' ? $map->without([$key]) : $map->with([$key], $value);
		}

		return $map->text();
	}

	/**
	 * JSON with `alt` and `caption` set, or removed when empty.
	 *
	 * @throws MediaException When the file isn't a JSON object.
	 */
	private static function json(string $text, MediaMetadata $metadata): string
	{
		try {
			$data = trim($text) === '' ? [] : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new MediaException('The metadata file isn\'t valid JSON; fix it by hand first.', previous: $error);
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new MediaException('The metadata file isn\'t a JSON object; fix it by hand first.');
		}

		foreach ($metadata->toArray() as $key => $value) {
			if ($value === '') {
				unset($data[$key]);
			} else {
				$data[$key] = $value;
			}
		}

		return $data === [] ? '' : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}
}
