<?php

/**
 * Media indexer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Closure;
use Psr\Clock\ClockInterface;
use Throwable;
use Blush\Core\Paths;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaResolver;
use Blush\Support\Filesystem;

/**
 * Builds the media index (D-238, D-288): every library file in
 * `user/media` (D-294: never beside entries), of the types the library lists (`MediaResolver::EXTENSIONS`) that the site
 * allows, never hidden ones, each with its type, size, dimensions,
 * metadata file's values, and, for an image, a sound, or a video, what
 * it says about itself (embedded metadata, D-289, D-291).
 *
 * An incremental run (the default) keeps a file's record when its size,
 * its modified time, and its metadata file's modified time haven't
 * changed, so an unchanged file isn't read at all; a full run reads every
 * file, and happens by itself when the media URL or allowed types
 * changed. Metadata files are found in one walk of `user/data/media`,
 * which also finds the ones whose media file is gone (`orphans`). The
 * index is written only when something changed. Each run also finds the
 * images that are sizes of another (`MediaVariants`, D-239).
 */
final readonly class MediaIndexer
{
	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private MediaIndex $index,
		private ClockInterface $clock,
		private EmbeddedMetadataReader $embedded,
		private MediaMetadataStore $store
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * Indexes the media. `$progress` is called after each file read with
	 * the number read and how many it will read. `$written` are the keys
	 * of files whose metadata was just written, which are read again even
	 * when the write left the modified time where it was (in the same
	 * second).
	 *
	 * `$limit` caps how many files one run reads (D-626): the files just
	 * written first, then new and changed ones, then those a rebuild has
	 * still to read. The files it doesn't get to keep their last records
	 * (a new one isn't listed yet), the rebuild's are kept in the index
	 * as `pending`, and the report says how many are left; the next run
	 * carries on. Without a limit, it reads everything it needs to.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @param  list<string>             $written
	 * @throws MediaException When the index can't be written.
	 */
	public function index(bool $full = false, ?Closure $progress = null, array $written = [], ?int $limit = null): MediaIndexReport
	{
		$fingerprint = $this->fingerprint();
		$previous    = $this->index->snapshot();
		$rebuild     = $full || $previous->fingerprint !== $fingerprint;
		$files       = $this->files();
		$described   = array_map(static fn (array $file): int => $file['modified'], $this->store->files());
		$reread      = array_flip($written);

		// A rebuild reads every file; one under way reads what it has left.
		$pending = $rebuild
			? array_map(strval(...), array_keys($files))
			: array_values(array_filter($previous->pending, static fn (string $key): bool => isset($files[$key])));
		$waiting = array_flip($pending);
		$stale   = [];

		foreach ($files as $key => [$size, $modified]) {
			$old  = $previous->records[$key] ?? null;
			$data = $described[$key] ?? null;

			$known = $old !== null || ($previous->rejected[$key] ?? null) === [$size, $modified];

			if (! isset($waiting[$key]) && (isset($reread[$key]) || ! $known || ($old !== null && ($old->size !== $size || $old->modified !== $modified || $old->described !== $data)))) {
				$stale[] = (string) $key;
			}
		}

		$first  = array_values(array_filter($written, static fn (string $key): bool => isset($files[$key])));
		$toRead = array_values(array_unique([...$first, ...$stale, ...$pending]));
		$read   = array_flip($limit === null ? $toRead : array_slice($toRead, 0, max(0, $limit)));
		$records  = [];
		$rejected = [];
		$done     = 0;

		foreach ($files as $key => [$size, $modified]) {
			if (isset($read[$key])) {
				$record = $this->record((string) $key, $modified, $described[$key] ?? null);

				if ($record !== null) {
					$records[$key] = $record;
				} else {
					$rejected[$key] = [$size, $modified];
				}

				$done++;

				if ($progress !== null) {
					$progress($done, count($read));
				}
			} elseif (isset($previous->records[$key])) {
				$records[$key] = $previous->records[$key];
			} elseif (isset($previous->rejected[$key])) {
				$rejected[$key] = $previous->rejected[$key];
			}
		}

		$pending = array_values(array_filter($pending, static fn (string $key): bool => ! isset($read[$key])));

		// Sizes of other images (D-239), which a file beside them can
		// change without changing themselves.
		$variants = MediaVariants::find($records);
		$added    = [];
		$changed  = [];

		foreach ($records as $key => $record) {
			if ($record->original !== ($variants[$key] ?? null)) {
				$records[$key] = $record = $record->withOriginal($variants[$key] ?? null);
			}

			$old = $previous->records[$key] ?? null;

			if ($old === null) {
				$added[] = $key;
			} elseif ($old !== $record && $old->toArray() !== $record->toArray()) {
				$changed[] = $key;
			}
		}

		$removed = array_map(strval(...), array_keys(array_diff_key($previous->records, $records)));
		$orphans = array_map(strval(...), array_keys(array_diff_key($described, $records)));
		sort($orphans);

		$save = $rebuild || $added !== [] || $changed !== [] || $removed !== [] || $orphans !== $previous->orphans || $pending !== $previous->pending || $rejected != $previous->rejected || ! $this->index->exists();

		if ($save) {
			$this->index->save(new MediaSnapshot($fingerprint, $this->clock->now()->getTimestamp(), $records, $orphans, $pending, $rejected));
		}

		return new MediaIndexReport(count($records), $added, $changed, $removed, $orphans, $save, count($toRead) - count($read));
	}

	/**
	 * What the index is built with: the media URL, allowed types, and
	 * embedded metadata readers. An index built with others is rebuilt.
	 */
	public function fingerprint(): string
	{
		return hash('xxh128', $this->config->url . '|' . implode(',', $this->config->types) . '|' . $this->embedded->fingerprint());
	}

	/**
	 * The media files to index, by key: their size and modified time.
	 *
	 * @return array<string, array{int, int}>
	 */
	private function files(): array
	{
		$found = [];

		foreach ($this->filesystem->files($this->paths->media) as $relative => $file) {
			$mime = MediaResolver::EXTENSIONS[strtolower($file->getExtension())] ?? null;

			if ($mime !== null && $this->config->allows($mime)) {
				$found[str_replace('\\', '/', (string) $relative)] = [(int) $file->getSize(), (int) $file->getMTime()];
			}
		}

		// In order, so the index and its reports don't depend on the walk.
		ksort($found, SORT_STRING);

		return $found;
	}

	/**
	 * Reads one media file's record, or `null` when it isn't one the
	 * library takes (its contents aren't an allowed type).
	 *
	 * @param ?int $described When its metadata last changed, if it has any.
	 */
	private function record(string $key, int $modified, ?int $described): ?MediaRecord
	{
		$file = $this->resolver->fromKey($key);

		if ($file === null) {
			return null;
		}

		$metadata = [];

		if ($described !== null) {
			try {
				$data = $this->store->load($key);

				foreach ($data as $name => $value) {
					$metadata[(string) $name] = $value;
				}
			} catch (Throwable) {
				// An unreadable metadata file counts as none, as the store reads it.
			}
		}

		// What an image, sound, video, or document says about itself
		// (D-289, D-291, D-551); a video's size comes from it.
		$embedded = MediaKind::fromMime($file->mime) !== MediaKind::File ? $this->embedded->read($file->path, $file->mime) : null;
		$said     = $embedded->values ?? [];
		$width    = $file->width ?? (is_int($said['width'] ?? null) ? $said['width'] : null);
		$height   = $file->height ?? (is_int($said['height'] ?? null) ? $said['height'] : null);

		return new MediaRecord($key, $file->url, $file->mime, $file->size, $modified, $width, $height, $metadata, $described, $embedded === null || $embedded->isEmpty() ? null : $embedded);
	}
}
