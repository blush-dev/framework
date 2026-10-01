<?php

/**
 * Media library.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Blush\Core\AppConfig;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadata;

/**
 * The media library, read from the media index (D-288). The index is
 * built on first use when it doesn't exist or was built with another
 * media URL or allowed types; in development (with
 * `MediaConfig::$autoIndex`), the first use in each request also runs an
 * incremental index, which only reads files that changed. Elsewhere,
 * reindexing is explicit: `media:index`, publishing, or the admin, which
 * refreshes it after an upload or a change to a file's metadata.
 */
final class MediaLibrary
{
	private bool $checked = false;

	public function __construct(
		private readonly MediaIndex $index,
		private readonly MediaIndexer $indexer,
		private readonly MediaConfig $config,
		private readonly AppConfig $app
	) {}

	/**
	 * Returns the index's contents, refreshed as the environment asks.
	 *
	 * @throws MediaException When the index can't be written.
	 */
	public function snapshot(): MediaSnapshot
	{
		if (! $this->checked) {
			$this->checked = true;
			$stale         = ! $this->index->exists() || $this->index->snapshot()->fingerprint !== $this->indexer->fingerprint();

			if ($stale || ($this->config->autoIndex && $this->app->environment->isDevelopment())) {
				$this->indexer->index();
			}
		}

		return $this->index->snapshot();
	}

	/**
	 * Refreshes the index now, after a change the admin made.
	 *
	 * @throws MediaException
	 */
	public function refresh(): MediaIndexReport
	{
		$this->checked = true;

		return $this->indexer->index();
	}

	/**
	 * Returns a file's record by its key.
	 *
	 * @throws MediaException
	 */
	public function find(string $key): ?MediaRecord
	{
		return $this->snapshot()->records[$key] ?? null;
	}

	/**
	 * Finds files, newest first (then by path), a page at a time.
	 *
	 * @throws MediaException
	 */
	public function query(MediaQuery $query): MediaResults
	{
		$search = mb_strtolower(trim($query->search));
		$found  = array_filter($this->snapshot()->records, static function (MediaRecord $record) use ($query, $search): bool {
			if ($query->kind !== null && $record->kind() !== $query->kind) {
				return false;
			}

			if ($query->missingAlt && ($record->kind() !== MediaKind::Image || $record->metadata()->alt !== '')) {
				return false;
			}

			return $search === '' || str_contains(mb_strtolower($record->key . "\n" . self::text($record->metadata)), $search);
		});

		usort($found, static fn (MediaRecord $a, MediaRecord $b): int => [$b->modified, $a->key] <=> [$a->modified, $b->key]);

		return new MediaResults(array_slice($found, max(0, $query->page - 1) * $query->per, $query->per), count($found));
	}

	/**
	 * A file's metadata as text to search: its values that are text,
	 * including lists of text.
	 *
	 * @param array<array-key, mixed> $values
	 */
	private static function text(array $values): string
	{
		$parts = [];

		foreach ($values as $value) {
			if (is_string($value) || is_int($value) || is_float($value)) {
				$parts[] = MediaMetadata::line((string) $value);
			} elseif (is_array($value)) {
				$parts[] = self::text($value);
			}
		}

		return implode("\n", $parts);
	}
}
