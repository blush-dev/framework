<?php

/**
 * Media upload dates.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Blush\Core\AppConfig;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Media\Index\MediaRecord;
use Blush\Support\Progress;
use Blush\Translation\Messages;

/**
 * When each media file was added to the library (D-709), the date the
 * library is ordered by, newest first. An upload writes it, as
 * `uploaded` in the file's metadata. A file without one (added by hand,
 * or before uploads were dated) is given a likely date, which the
 * library orders it by and `media:dates --write` writes for it:
 *
 * - **A dated folder** (`2006/04/`, `2006/04/15/`, or `2006/`, anywhere
 *   in its path) says when, as uploads are kept by default
 *   (`{year}/{month}`). The file's modified time wins when it falls in
 *   that time, then the date the file says it was made (`created`,
 *   D-289); otherwise it's the start of it, since a copied file's
 *   modified time is when it was copied.
 * - **Otherwise,** its modified time.
 *
 * A file's modified time alone isn't trusted, since copying, syncing,
 * and checking files out change it. Sizes of another image (D-239)
 * belong to it and aren't dated.
 */
final readonly class MediaDates
{
	/**
	 * A dated folder: a year, then a month, then a day, each optional
	 * after the year.
	 */
	private const string FOLDER = '#(?:^|/)((?:19|20)\d{2})(?:/(0[1-9]|1[0-2])(?:/(0[1-9]|[12]\d|3[01]))?)?/#';

	public function __construct(
		private MediaIndexer $indexer,
		private MediaIndex $index,
		private MediaMetadataStore $store,
		private MediaResolver $resolver,
		private AppConfig $app,
		private Messages $messages
	) {}

	/**
	 * Returns the files whose upload date needs writing, each with the
	 * one it'd be given, by key: those without one (given a likely one),
	 * and those with one not written in UTC (D-711), which then sort
	 * wrongly as text in the media table (given it in UTC).
	 *
	 * @param  ?Closure(string, int, int): void $progress How it's going (`Progress`).
	 * @return array<string, DateTimeImmutable>
	 * @throws MediaException When the index can't be written.
	 */
	public function missing(?Closure $progress = null): array
	{
		$this->indexer->index(progress: new Progress($progress)->of('Checking the media index'));

		$timezone = $this->app->timezone();
		$missing  = [];

		foreach ($this->index->snapshot()->records as $key => $record) {
			if ($record->isVariant()) {
				continue;
			}

			$uploaded = $record->metadata()->uploaded;

			if ($uploaded === null) {
				$missing[(string) $key] = self::guess($record, $timezone);
			} elseif ($record->metadata[MediaMetadata::UPLOADED] !== MediaMetadata::utc($uploaded)) {
				$missing[(string) $key] = $uploaded;
			}
		}

		return $missing;
	}

	/**
	 * Writes the upload date of every file that needs one written (the
	 * likely one, or its own in UTC; `missing()`), or only those
	 * `$allowed` passes (by key), such as the ones an account may edit,
	 * then reindexes.
	 *
	 * @param  ?Closure(string): bool                $allowed
	 * @param  ?Closure(string, int, int): void $progress How it's going (`Progress`).
	 * @throws MediaException When the index can't be written.
	 */
	public function fill(?Closure $allowed = null, ?Closure $progress = null): FilledMediaDates
	{
		$dates   = [];
		$failed  = [];
		$missing = $this->missing($progress);
		$steps   = new Progress($progress);

		if ($allowed !== null) {
			$missing = array_filter($missing, $allowed, ARRAY_FILTER_USE_KEY);
		}

		$done = 0;

		foreach ($missing as $key => $date) {
			$steps->step('Writing upload dates', $done++, count($missing));

			$file = $this->resolver->fromKey($key);

			if ($file === null) {
				$failed[$key] = (string) $this->messages->t('media.common.not_in_library', 'isn\'t in the media library.', context: 'After a media file\'s path, as why it couldn\'t be fixed.');
				continue;
			}

			// A metadata file that can't be read isn't written over.
			if (! $this->store->isWritable($file)) {
				$failed[$key] = (string) $this->messages->t('media.common.metadata_unreadable', 'its metadata file can\'t be read; fix it first (content:lint says why).', context: 'After a media file\'s path, as why it couldn\'t be fixed.');
				continue;
			}

			$value = MediaMetadata::utc($date);

			try {
				$this->store->save($file, [MediaMetadata::UPLOADED => $value]);
			} catch (MediaException $error) {
				$failed[$key] = $error->getMessage();
				continue;
			}

			$dates[$key] = $value;
		}

		$steps->step('Writing upload dates', $done, count($missing));

		return new FilledMediaDates($dates, $failed, $this->indexer->index(progress: $steps->of('Updating the media index'), written: array_map(strval(...), array_keys($dates))));
	}

	/**
	 * When a file was added to the library: its upload date, else its
	 * likely one.
	 */
	public static function of(MediaRecord $record, DateTimeZone $timezone): DateTimeImmutable
	{
		return $record->metadata()->uploaded ?? self::guess($record, $timezone);
	}

	/**
	 * When a file was likely added to the library, from its folder, its
	 * modified time, and the date it says it was made.
	 */
	public static function guess(MediaRecord $record, DateTimeZone $timezone): DateTimeImmutable
	{
		$modified = new DateTimeImmutable('@' . $record->modified)->setTimezone($timezone);

		if (preg_match(self::FOLDER, $record->key, $folder) !== 1) {
			return $modified;
		}

		$month = ($folder[2] ?? '') !== '' ? $folder[2] : '';
		$day   = ($folder[3] ?? '') !== '' ? $folder[3] : '';
		$start = new DateTimeImmutable(sprintf('%s-%s-%s 00:00:00', $folder[1], $month === '' ? '01' : $month, $day === '' ? '01' : $day), $timezone);
		$end   = $start->modify($day !== '' ? '+1 day' : ($month !== '' ? '+1 month' : '+1 year'));

		foreach ([$modified, self::created($record, $timezone)] as $date) {
			if ($date !== null && $date >= $start && $date < $end) {
				return $date;
			}
		}

		return $start;
	}

	/**
	 * The date and time the file says it was made (D-289), when it says
	 * a whole date.
	 */
	private static function created(MediaRecord $record, DateTimeZone $timezone): ?DateTimeImmutable
	{
		$created = $record->embedded?->values['created'] ?? null;

		if (! is_string($created) || preg_match('/^\d{4}-\d{2}-\d{2}/', $created) !== 1) {
			return null;
		}

		try {
			return new DateTimeImmutable(str_replace(' Z', 'Z', $created), $timezone)->setTimezone($timezone);
		} catch (Exception) {
			return null;
		}
	}
}
