<?php

/**
 * Media metadata check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Throwable;
use Blush\Content\Lint\Linter;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Field\FieldContext;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Media\Index\MediaIndex;

/**
 * Checks the metadata files under `user/data/media` (D-238, D-293), as
 * `content:lint` reports them, by their path from the site root
 * (`user/data/media/2024/sunset.jpg.yml`). The media library reads a bad
 * one as none, so this is where a site hears about it:
 *
 * - errors: a file that can't be read, or isn't a map of fields, and a
 *   value that doesn't fit its field (alt text as a list); and ids
 *   (D-487): a media file with none (reported by the media file's own
 *   path, since it may have no metadata file), one that isn't a UUID,
 *   and one another file has too (`MediaIds`); and `sizes` (D-488) that
 *   isn't a map of files to their width and height;
 * - warnings: a file whose media file is gone (renamed or deleted
 *   without it), or isn't a type the site allows, a file hidden by
 *   one in another format (`sunset.jpg.json` beside a `.yml`), and one
 *   describing a size of another image (D-239), whose details are that
 *   image's, and listed sizes that aren't the image's (`MediaSizes`);
 * - notices: keys that aren't one of the kind's fields, and aliases.
 */
final readonly class MediaMetadataCheck
{
	public function __construct(
		private Paths $paths,
		private MediaMetadataStore $store,
		private MediaResolver $resolver,
		private MediaSchemas $schemas,
		private DataLoader $data,
		private AppConfig $app,
		private MediaIds $ids,
		private MediaSizes $sizes,
		private MediaIndex $index
	) {}

	/**
	 * Checks every metadata file, returning the number checked and each
	 * one's violations, by its path from the site root.
	 *
	 * @return array{int, array<string, list<Violation>>}
	 */
	public function check(): array
	{
		$files      = $this->store->files();
		$violations = [];

		foreach ($files as $key => $file) {
			$path = $this->paths->relative($file['path']);

			$violations[$path] = $this->checkFile((string) $key, $file['path']);

			foreach ($file['shadowed'] as $hidden) {
				$violations[$this->paths->relative($hidden)] = [new Violation(Linter::FILE, sprintf('is hidden by %s, which is read instead; merge them into one file.', basename($file['path'])), Severity::Warning)];
			}
		}

		// A file that can't be read already says so; its id can't be told.
		$unreadable = array_filter($files, fn (array $file): bool => array_any(
			$violations[$this->paths->relative($file['path'])] ?? [],
			static fn (Violation $violation): bool => $violation->field === Linter::FILE && $violation->severity === Severity::Error
		));

		foreach ($this->checkIds(array_diff_key($files, $unreadable), array_keys($unreadable)) as $path => $found) {
			$violations[$path] = [...$violations[$path] ?? [], ...$found];
		}

		return [count($files) + array_sum(array_map(static fn (array $file): int => count($file['shadowed']), $files)), $violations];
	}

	/**
	 * Checks every media file's id (D-487), and the metadata files of
	 * sizes of other images, by path from the site root.
	 *
	 * @param  array<string, array{path: string, modified: int, shadowed: list<string>}> $files      The readable metadata files, by key.
	 * @param  list<array-key>                                                           $unreadable The keys of the others.
	 * @return array<string, list<Violation>>
	 */
	private function checkIds(array $files, array $unreadable): array
	{
		try {
			$report = $this->ids->report();
		} catch (MediaException) {
			// The index can't be written; media:index says why.
			return [];
		}

		$records    = $this->index->snapshot()->records;
		$media      = fn (string $key): string => $this->paths->relative("{$this->paths->media}/{$key}");
		$violations = [];

		foreach (array_diff($report->missing, array_map(strval(...), $unreadable)) as $key) {
			if (isset($files[$key]) && array_key_exists(MediaMetadata::ID, $records[$key]->metadata ?? [])) {
				$violations[$this->paths->relative($files[$key]['path'])][] = new Violation(MediaMetadata::ID, 'isn\'t a UUID; give the file a new one with media:ids --write, or on Content health in the admin.');
			} else {
				$violations[$media($key)][] = new Violation(MediaMetadata::ID, 'has no id; add one with media:ids --write, or on Content health in the admin.');
			}
		}

		foreach ($report->duplicates as $keys) {
			$shown = array_map(fn (string $key): string => isset($files[$key]) ? $this->paths->relative($files[$key]['path']) : $media($key), $keys);

			foreach ($shown as $path) {
				$violations[$path][] = new Violation(MediaMetadata::ID, sprintf('is also the id of %s; keep it on one file and give the others new ones with media:ids --keep, or on Content health in the admin.', implode(', ', array_diff($shown, [$path]))));
			}
		}

		try {
			$stale = $this->sizes->report()->stale;
		} catch (MediaException) {
			$stale = [];
		}

		foreach ($stale as $key => $listed) {
			if (isset($files[$key])) {
				foreach ($listed as $size) {
					$violations[$this->paths->relative($files[$key]['path'])][] = new Violation(MediaMetadata::SIZES, sprintf('lists %s, which isn\'t one of its sizes (it\'s gone, or another image\'s); record them again with media:sizes --write, or on Content health in the admin.', $media($size)), Severity::Warning);
				}
			}
		}

		foreach ($records as $key => $record) {
			if ($record->original !== null && isset($files[$key])) {
				$violations[$this->paths->relative($files[$key]['path'])][] = new Violation(Linter::FILE, sprintf('describes %s, a size of %s, whose details are read instead; move these there, or give this file an id of its own to keep it apart.', $media((string) $key), $media($record->original)), Severity::Warning);
			}
		}

		return $violations;
	}

	/**
	 * Checks one metadata file against the media file it describes.
	 *
	 * @return list<Violation>
	 */
	private function checkFile(string $key, string $path): array
	{
		try {
			$data = $this->data->loadFile($path);
		} catch (Throwable $error) {
			// The loader names the file; the report already does.
			$message = $error->getMessage();
			$message = str_starts_with($message, "{$path}: ") ? substr($message, strlen($path) + 2) : $message;

			return [new Violation(Linter::FILE, sprintf('can\'t be read, so the file has no details: %s', $message))];
		}

		if ($data !== [] && array_is_list($data)) {
			return [new Violation(Linter::FILE, 'isn\'t a map of fields, so the file has no details.')];
		}

		$media = $this->resolver->fromKey($key);

		if ($media === null) {
			return [$this->orphan($key)];
		}

		$violations = $this->schemas->forFile($media)->resolve(MediaMetadata::fromArray($data)->fields(), new FieldContext($this->app->timezone()))->violations;

		if (array_key_exists(MediaMetadata::SIZES, $data) && ! self::sizesFit($data[MediaMetadata::SIZES])) {
			$violations[] = new Violation(MediaMetadata::SIZES, 'isn\'t a map of each size\'s file to its width and height; record them again with media:sizes --write.');
		}

		return $violations;
	}

	/**
	 * Returns whether recorded sizes are a map of keys to maps of a whole
	 * number `width` and `height`.
	 */
	private static function sizesFit(mixed $sizes): bool
	{
		if (! is_array($sizes) || ($sizes !== [] && array_is_list($sizes))) {
			return false;
		}

		return array_all($sizes, static fn (mixed $size): bool => is_array($size) && is_int($size['width'] ?? null) && is_int($size['height'] ?? null));
	}

	/**
	 * The warning for a metadata file whose media file is gone, or isn't
	 * one the site allows.
	 */
	private function orphan(string $key): Violation
	{
		$media = "{$this->paths->media}/{$key}";
		$shown = $this->paths->relative($media);

		return new Violation(Linter::FILE, is_file($media)
			? sprintf('describes %s, which isn\'t a type of media the site allows.', $shown)
			: sprintf('describes %s, which isn\'t there; move this file with its media file, or delete it.', $shown), Severity::Warning);
	}
}
