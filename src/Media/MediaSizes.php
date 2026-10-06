<?php

/**
 * Media sizes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Media\Index\MediaRecord;

/**
 * Records each image's sizes in its metadata file (D-488), for
 * `media:sizes` and Site Health in the admin. The media index finds
 * sizes by their names (D-239) when they aren't listed; recording
 * them writes what it found as `sizes`, each file's key mapped to its
 * width and height, so it's known from then on rather than guessed:
 *
 *     sizes:
 *       2019/photo-150x100.jpg: { width: 150, height: 100 }
 *       2019/photo-300x200.jpg: { width: 300, height: 200 }
 *     id: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
 *
 * An image's list is written whole: its sizes now, smallest first, so a
 * size that's gone leaves it, and an image with none loses the key. It
 * reads the media index, brought up to date first.
 */
final readonly class MediaSizes
{
	public function __construct(
		private MediaIndexer $indexer,
		private MediaIndex $index,
		private MediaMetadataStore $store,
		private MediaResolver $resolver
	) {}

	/**
	 * Returns the images whose lists aren't their sizes.
	 *
	 * @throws MediaException When the index can't be written.
	 */
	public function report(): MediaSizeReport
	{
		$this->indexer->index();

		$records    = $this->index->snapshot()->records;
		$unrecorded = [];
		$stale      = [];

		foreach ($this->current($records) as $key => $sizes) {
			$listed = isset($records[$key]) ? $records[$key]->metadata()->sizes : [];
			$add    = array_keys(array_filter($sizes, static fn (array $size, string $name): bool => ($listed[$name] ?? null) !== $size, ARRAY_FILTER_USE_BOTH));
			$remove = array_keys(array_diff_key($listed, $sizes));

			if ($add !== []) {
				$unrecorded[$key] = array_map(strval(...), $add);
			}

			if ($remove !== []) {
				$stale[$key] = array_map(strval(...), $remove);
			}
		}

		ksort($unrecorded, SORT_STRING);
		ksort($stale, SORT_STRING);

		return new MediaSizeReport($unrecorded, $stale);
	}

	/**
	 * Writes the sizes of every image whose list isn't them, or only those
	 * `$allowed` passes (by key), such as the ones an account may edit.
	 *
	 * @param  ?Closure(string): bool $allowed
	 * @throws MediaException When the index can't be written.
	 */
	public function record(?Closure $allowed = null): RecordedMediaSizes
	{
		$images  = $this->report()->images();
		$current = $this->current($this->index->snapshot()->records);
		$written = [];
		$failed  = [];

		foreach ($allowed === null ? $images : array_filter($images, $allowed) as $key) {
			$file = $this->resolver->fromKey($key);

			if ($file === null) {
				$failed[$key] = 'isn\'t in the media library.';
				continue;
			}

			if (! $this->store->isWritable($file)) {
				$failed[$key] = 'its metadata file can\'t be read; fix it first (content:lint says why).';
				continue;
			}

			$sizes = $current[$key] ?? [];

			try {
				$this->store->save($file, [MediaMetadata::SIZES => $sizes === [] ? null : $sizes]);
			} catch (MediaException $error) {
				$failed[$key] = $error->getMessage();
				continue;
			}

			$written[$key] = array_map(strval(...), array_keys($sizes));
		}

		return new RecordedMediaSizes($written, $failed, $this->indexer->index(written: array_map(strval(...), array_keys($written))));
	}

	/**
	 * Each image's sizes as the index has them, smallest first, with
	 * every image that lists sizes (an empty list when none are its own
	 * any more).
	 *
	 * @param  array<string, MediaRecord> $records
	 * @return array<string, array<string, array{width: ?int, height: ?int}>>
	 */
	private function current(array $records): array
	{
		$sizes = [];

		foreach ($records as $key => $record) {
			if ($record->metadata()->sizes !== []) {
				$sizes[(string) $key] ??= [];
			}

			if ($record->original !== null) {
				$sizes[$record->original][(string) $key] = ['width' => $record->width, 'height' => $record->height];
			}
		}

		foreach ($sizes as &$list) {
			uksort($list, static fn (string $a, string $b): int => [($list[$a]['width'] ?? 0) * ($list[$a]['height'] ?? 0), $a] <=> [($list[$b]['width'] ?? 0) * ($list[$b]['height'] ?? 0), $b]);
		}

		unset($list);

		return $sizes;
	}
}
