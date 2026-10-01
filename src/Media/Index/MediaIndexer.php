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
use Blush\Data\DataLoader;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
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
 * index is written only when something changed.
 */
final readonly class MediaIndexer
{
	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private MediaIndex $index,
		private DataLoader $data,
		private ClockInterface $clock,
		private EmbeddedMetadataReader $embedded,
		private MediaMetadataStore $store
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * Indexes the media. `$progress` is called after each file with the
	 * number done and the total.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @throws MediaException When the index can't be written.
	 */
	public function index(bool $full = false, ?Closure $progress = null): MediaIndexReport
	{
		$fingerprint = $this->fingerprint();
		$previous    = $this->index->snapshot();
		$full        = $full || $previous->fingerprint !== $fingerprint;
		$files       = $this->files();
		$described   = array_map(static fn (array $file): array => [$file['path'], $file['modified']], $this->store->files());
		$records     = [];
		$added       = [];
		$changed     = [];
		$done        = 0;

		foreach ($files as $key => [$size, $modified]) {
			$old     = $previous->records[$key] ?? null;
			$written = $described[$key] ?? null;

			if (! $full && $old !== null && $old->size === $size && $old->modified === $modified && $old->described === ($written[1] ?? null)) {
				$records[$key] = $old;
			} else {
				$record = $this->record($key, $modified, $written);

				if ($record !== null) {
					$records[$key] = $record;

					if ($old === null) {
						$added[] = $key;
					} elseif ($old->toArray() !== $record->toArray()) {
						$changed[] = $key;
					}
				}
			}

			$done++;

			if ($progress !== null) {
				$progress($done, count($files));
			}
		}

		$removed = array_map(strval(...), array_keys(array_diff_key($previous->records, $records)));
		$orphans = array_map(strval(...), array_keys(array_diff_key($described, $records)));
		sort($orphans);

		$written = $full || $added !== [] || $changed !== [] || $removed !== [] || $orphans !== $previous->orphans || ! $this->index->exists();

		if ($written) {
			$this->index->save(new MediaSnapshot($fingerprint, $this->clock->now()->getTimestamp(), $records, $orphans));
		}

		return new MediaIndexReport(count($records), $added, $changed, $removed, $orphans, $written);
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
	 * @param ?array{string, int} $written Its metadata file, if it has one.
	 */
	private function record(string $key, int $modified, ?array $written): ?MediaRecord
	{
		$file = $this->resolver->fromKey($key);

		if ($file === null) {
			return null;
		}

		$metadata = [];

		if ($written !== null) {
			try {
				$data = $this->data->loadFile($written[0]);

				foreach ($data as $name => $value) {
					$metadata[(string) $name] = $value;
				}
			} catch (Throwable) {
				// An unreadable metadata file counts as none, as the store reads it.
			}
		}

		// What an image, sound, or video says about itself (D-289, D-291);
		// a video's size comes from it.
		$embedded = in_array($file->type(), ['image', 'audio', 'video'], true) ? $this->embedded->read($file->path, $file->mime) : null;
		$said     = $embedded->values ?? [];
		$width    = $file->width ?? (is_int($said['width'] ?? null) ? $said['width'] : null);
		$height   = $file->height ?? (is_int($said['height'] ?? null) ? $said['height'] : null);

		return new MediaRecord($key, $file->url, $file->mime, $file->size, $modified, $width, $height, $metadata, $written[1] ?? null, $embedded === null || $embedded->isEmpty() ? null : $embedded);
	}
}
