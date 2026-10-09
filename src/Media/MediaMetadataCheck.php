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
use Blush\Field\FieldContext;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Media\Index\MediaIndex;
use Blush\Support\Uuid;

/**
 * Checks the metadata files under `user/data/media` (D-238, D-293), as
 * `content:lint` reports them, by their path from the site root
 * (`user/data/media/2024/sunset.jpg.json`). The media library reads a bad
 * one as none, so this is where a site hears about it:
 *
 * - errors: a file that can't be read, or isn't a map of fields, and a
 *   value that doesn't fit its field (alt text as a list); and ids
 *   (D-487): a media file with none (reported by the media file's own
 *   path, since it may have no metadata file), one that isn't a UUID,
 *   and one another file has too (`MediaIds`); and `sizes` (D-488) that
 *   isn't a map of files to their width and height; and an `artwork`
 *   (D-581) that isn't a UUID;
 * - warnings: a file whose media file is gone (renamed or deleted
 *   without it), or isn't a type the site allows, and one
 *   describing a size of another image (D-239), whose details are that
 *   image's, and listed sizes that aren't the image's (`MediaSizes`);
 *   and artwork naming no image in the library, or on a file that
 *   isn't a sound or video;
 * - notices: keys that aren't one of the kind's fields, and aliases.
 */
final readonly class MediaMetadataCheck
{
	public function __construct(
		private Paths $paths,
		private MediaMetadataStore $store,
		private MediaResolver $resolver,
		private MediaSchemas $schemas,
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
		[$violations, $unreadable, $checked] = $this->checkSome($this->keys());

		foreach ($this->checkLibrary($unreadable) as $path => $found) {
			$violations[$path] = [...$violations[$path] ?? [], ...$found];
		}

		return [$checked, $violations];
	}

	/**
	 * Returns the media keys that have metadata files, for checking them
	 * a batch at a time (D-626).
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return array_map(strval(...), array_keys($this->store->files()));
	}

	/**
	 * Checks some metadata files on their own, by media key: each one's
	 * values, and files hidden by it. Returns the violations by path from
	 * the site root, the keys of files that can't be read, and how many
	 * files were checked.
	 *
	 * @param  list<string> $keys
	 * @return array{array<string, list<Violation>>, list<string>, int}
	 */
	public function checkSome(array $keys): array
	{
		$files      = array_intersect_key($this->store->files(), array_flip($keys));
		$violations = [];
		$unreadable = [];
		$checked    = 0;

		foreach ($files as $key => $file) {
			$path  = $file['location'];
			$found = $this->checkFile((string) $key, $path);

			$violations[$path] = $found;
			$checked++;

			// A file that can't be read already says so; its id can't be told.
			if (array_any($found, static fn (Violation $violation): bool => $violation->field === Linter::FILE && $violation->severity === Severity::Error)) {
				$unreadable[] = (string) $key;
			}
		}

		return [$violations, $unreadable, $checked];
	}

	/**
	 * Checks what needs the whole library, by path from the site root:
	 * ids, sizes, artwork, and files describing a size of another image.
	 * `$unreadable` are the keys of metadata files that can't be read,
	 * whose ids can't be told.
	 *
	 * @param  list<string> $unreadable
	 * @return array<string, list<Violation>>
	 */
	public function checkLibrary(array $unreadable): array
	{
		$files = $this->store->files();

		return $this->checkIds(array_diff_key($files, array_flip($unreadable)), $unreadable);
	}

	/**
	 * Checks every media file's id (D-487), and the metadata files of
	 * sizes of other images, by path from the site root.
	 *
	 * @param  array<array-key, array{location: string, modified: int}> $files      The readable metadata files, by key.
	 * @param  list<string>                                              $unreadable The keys of the others.
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
				$violations[$files[$key]['location']][] = new Violation(MediaMetadata::ID, 'isn\'t a UUID; give the file a new one with media:ids --write, or on Site Health in the admin.', kind: ViolationKind::Id);
			} else {
				$violations[$media($key)][] = new Violation(MediaMetadata::ID, 'has no id; add one with media:ids --write, or on Site Health in the admin.', kind: ViolationKind::Id);
			}
		}

		foreach ($report->duplicates as $keys) {
			$shown = array_map(fn (string $key): string => isset($files[$key]) ? $files[$key]['location'] : $media($key), $keys);

			foreach ($shown as $path) {
				$violations[$path][] = new Violation(MediaMetadata::ID, sprintf('is also the id of %s; keep it on one file and give the others new ones with media:ids --keep, or on Site Health in the admin.', implode(', ', array_diff($shown, [$path]))), kind: ViolationKind::Id);
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
					$violations[$files[$key]['location']][] = new Violation(MediaMetadata::RENDITIONS, sprintf('lists %s, which isn\'t one of its sizes (it\'s gone, or another image\'s); record them again with media:sizes --write, or on Site Health in the admin.', $media($size)), Severity::Warning, ViolationKind::Sizes);
				}
			}
		}

		// Artwork names a library image by id (D-581).
		$images = [];

		foreach ($records as $record) {
			if ($record->original === null && $record->kind() === MediaKind::Image && $record->id() !== null) {
				$images[$record->id()] = true;
			}
		}

		foreach ($records as $key => $record) {
			$artwork = $record->metadata()->artwork;

			if ($artwork === '' || ! isset($files[$key])) {
				continue;
			}

			$problem = match (true) {
				! MediaArtwork::has($record->kind()) => 'is only for sounds and videos; this file never shows it.',
				! isset($images[$artwork])           => 'names an image that isn\'t in the media library; choose another on the file\'s screen in the admin, or remove it.',
				default                              => null
			};

			if ($problem !== null) {
				$violations[$files[$key]['location']][] = new Violation(MediaMetadata::ARTWORK, $problem, Severity::Warning, ViolationKind::Artwork);
			}
		}

		foreach ($records as $key => $record) {
			if ($record->original !== null && isset($files[$key])) {
				$violations[$files[$key]['location']][] = new Violation(Linter::FILE, sprintf('describes %s, a size of %s, whose details are read instead; move these there, or give this file an id of its own to keep it apart.', $media((string) $key), $media($record->original)), Severity::Warning, ViolationKind::Details);
			}
		}

		return $violations;
	}

	/**
	 * Checks one metadata file against the media file it describes.
	 *
	 * @return list<Violation>
	 */
	private function checkFile(string $key, string $location): array
	{
		try {
			$data = $this->store->load($key);
		} catch (Throwable $error) {
			// The store names the record; the report already does.
			$message = $error->getMessage();
			$message = str_starts_with($message, "{$location}: ") ? substr($message, strlen($location) + 2) : $message;

			return [new Violation(Linter::FILE, sprintf('can\'t be read, so the file has no details: %s', $message), kind: ViolationKind::Unreadable)];
		}

		if ($data !== [] && array_is_list($data)) {
			return [new Violation(Linter::FILE, 'isn\'t a map of fields, so the file has no details.', kind: ViolationKind::Unreadable)];
		}

		$media = $this->resolver->fromKey($key);

		if ($media === null) {
			return [$this->orphan($key)];
		}

		$violations = $this->schemas->forFile($media)->resolve(MediaMetadata::fromArray($data)->fields(), new FieldContext($this->app->timezone()))->violations;

		if (array_key_exists(MediaMetadata::ARTWORK, $data) && ! Uuid::isValid($data[MediaMetadata::ARTWORK])) {
			$violations[] = new Violation(MediaMetadata::ARTWORK, 'isn\'t a library image\'s id; choose the artwork again on the file\'s screen in the admin.', kind: ViolationKind::Artwork);
		}

		if (array_key_exists(MediaMetadata::RENDITIONS, $data) && ! self::sizesFit($data[MediaMetadata::RENDITIONS])) {
			$violations[] = new Violation(MediaMetadata::RENDITIONS, 'isn\'t a map of each size\'s file to its width and height; record them again with media:sizes --write.', kind: ViolationKind::Value);
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
			: sprintf('describes %s, which isn\'t there; move this file with its media file, or delete it.', $shown), Severity::Warning, ViolationKind::Details);
	}
}
