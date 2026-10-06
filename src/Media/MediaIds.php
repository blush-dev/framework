<?php

/**
 * Media ids.
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
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Support\Uuid;

/**
 * Finds and fixes the media files whose ids (D-487) are missing or
 * shared, for `media:ids` and Site Health in the admin, as `EntryIds`
 * does for content (D-478). Every original has an id, kept in its
 * metadata file under `user/data/media`; a size of another image
 * (`MediaVariants`, D-239) has none, since it belongs to that image.
 *
 * - **Missing:** a file with no `id`, or one that isn't a UUID, is given
 *   a new one, which writes its metadata file when it has none.
 * - **Shared:** files with one id (a copied metadata file) aren't
 *   guessed at. Whoever fixes them says which file keeps it, and the
 *   others are given new ones.
 *
 * It reads the media index, brought up to date first. A file whose
 * metadata file can't be read is left for `content:lint` to report.
 */
final readonly class MediaIds
{
	public function __construct(
		private MediaIndexer $indexer,
		private MediaIndex $index,
		private MediaMetadataStore $store,
		private MediaResolver $resolver,
		private ClockInterface $clock
	) {}

	/**
	 * Returns the files missing a valid id, and the ids files share.
	 *
	 * @throws MediaException When the index can't be written.
	 */
	public function report(): MediaIdReport
	{
		$this->indexer->index();

		$missing = [];
		$ids     = [];

		foreach ($this->index->snapshot()->records as $key => $record) {
			if ($record->isVariant()) {
				continue;
			}

			$id = $record->id();

			if ($id === null) {
				$missing[] = (string) $key;
			} else {
				$ids[$id][] = (string) $key;
			}
		}

		return new MediaIdReport($missing, array_filter($ids, static fn (array $keys): bool => count($keys) > 1));
	}

	/**
	 * Gives every file missing a valid id a new one, or only those
	 * `$allowed` passes (by key), such as the ones an account may edit.
	 *
	 * @param  ?Closure(string): bool $allowed
	 * @throws MediaException When the index can't be written.
	 */
	public function assignMissing(?Closure $allowed = null): AssignedMediaIds
	{
		$missing = $this->report()->missing;

		return $this->assign($allowed === null ? $missing : array_values(array_filter($missing, $allowed)));
	}

	/**
	 * Keeps a file's id on it and gives the other files sharing the id
	 * new ones, or only those `$allowed` passes (by key).
	 *
	 * @param  ?Closure(string): bool $allowed
	 * @throws MediaException When the file doesn't share its id, or the index can't be written.
	 */
	public function keep(string $key, ?Closure $allowed = null): AssignedMediaIds
	{
		$key    = trim($key, '/');
		$shared = array_find($this->report()->duplicates, static fn (array $keys): bool => in_array($key, $keys, true));

		if ($shared === null) {
			throw new MediaException(sprintf('%s doesn\'t share its id with another media file.', $key));
		}

		$others = array_values(array_diff($shared, [$key]));

		return $this->assign($allowed === null ? $others : array_values(array_filter($others, $allowed)));
	}

	/**
	 * Gives each file a new id, then reindexes.
	 *
	 * @param  list<string> $keys
	 * @throws MediaException When the index can't be written.
	 */
	private function assign(array $keys): AssignedMediaIds
	{
		$ids    = [];
		$failed = [];

		foreach ($keys as $key) {
			$file = $this->resolver->fromKey($key);

			if ($file === null) {
				$failed[$key] = 'isn\'t in the media library.';
				continue;
			}

			// A metadata file that can't be read isn't written over.
			if (! $this->store->isWritable($file)) {
				$failed[$key] = 'its metadata file can\'t be read; fix it first (content:lint says why).';
				continue;
			}

			$id = Uuid::v7($this->clock->now());

			try {
				$this->store->save($file, [MediaMetadata::ID => $id]);
			} catch (MediaException $error) {
				$failed[$key] = $error->getMessage();
				continue;
			}

			$ids[$key] = $id;
		}

		return new AssignedMediaIds($ids, $failed, $this->indexer->index(written: array_map(strval(...), array_keys($ids))));
	}
}
