<?php

/**
 * Content indexer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Content\Events\ContentIndexed;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Event\Dispatcher;

/**
 * Builds the content index from the source. An incremental run (the
 * default) compares each file with its record: an unchanged modification
 * time and size means the file isn't read at all; a matching hash means it
 * was only touched, and the record keeps its data. Everything else is
 * parsed again. A full run parses every file, and happens by itself when
 * the index was built with different content types, timezone, or locale
 * (`IndexFingerprint`).
 *
 * The index is stored only when something changed, and `ContentIndexed`
 * is dispatched when it is.
 */
final readonly class Indexer
{
	public function __construct(
		private ContentSource $source,
		private ContentIndex $index,
		private RecordBuilder $builder,
		private IndexFingerprint $fingerprint,
		private ClockInterface $clock,
		private Dispatcher $events
	) {}

	/**
	 * Indexes the source. `$progress` is called after each file with the
	 * number done and the total. Files named in `$written` (paths) are
	 * read whatever their stat says: a writer that just changed them
	 * knows, and an edit that keeps a file's size within the second its
	 * time was last read would look untouched (a new id, D-477, is always
	 * the length of the old one).
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @param  list<string>             $written
	 * @throws IndexException When the index can't be stored.
	 * @throws UnreadableSource When the source can't be listed.
	 */
	public function index(bool $full = false, ?Closure $progress = null, array $written = []): IndexReport
	{
		$written     = array_flip($written);
		$fingerprint = $this->fingerprint->value();
		$previous    = $this->index->snapshot();
		$full        = $full || $previous->fingerprint !== $fingerprint;
		$files       = $this->source->files();
		$count       = count($files);
		$records     = [];
		$added       = [];
		$changed     = [];
		$failures    = [];
		$touched     = false;

		foreach ($files as $done => $file) {
			$old = $previous->records[$file->path] ?? null;

			try {
				if (! $full && $old !== null && ! isset($written[$file->path]) && $old['modified'] === $file->modified && $old['size'] === $file->size) {
					$records[] = IndexRecord::fromArray($old);

					continue;
				}

				$contents = $this->source->read($file->path);

				if (! $full && $old !== null && $old['hash'] === RecordBuilder::hash($contents)) {
					$records[] = IndexRecord::fromArray($old)->withSource($file);
					$touched   = true;

					continue;
				}

				$record    = $this->builder->build($file, $contents)->record;
				$records[] = $record;

				if ($old === null) {
					$added[] = $record->path;
				} elseif ($old['hash'] !== $record->hash) {
					$changed[] = $record->path;
				}
			} catch (InvalidDocument | UnreadableSource $e) {
				$failures[$file->path] = $e->getMessage();
			} finally {
				if ($progress !== null) {
					$progress($done + 1, $count);
				}
			}
		}

		$current = array_flip(array_map(static fn (IndexRecord $record): string => $record->path, $records));
		$removed = array_values(array_filter(
			array_map(strval(...), array_keys($previous->records)),
			static fn (string $path): bool => ! isset($current[$path])
		));

		$write = $full || $touched || $added !== [] || $changed !== [] || $removed !== [] || ! $this->index->exists();

		if ($write) {
			$this->index->save(IndexSnapshot::build($records, $fingerprint, $this->clock->now()->getTimestamp()));
		}

		$report = new IndexReport(count($records), $added, $changed, $removed, $failures, $full, $write);

		if ($write) {
			$this->events->dispatch(new ContentIndexed($report));
		}

		return $report;
	}
}
