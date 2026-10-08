<?php

/**
 * Media index job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Media\Index\MediaIndexer;

/**
 * Brings the media index up to date a batch at a time (`blush/media-index`,
 * D-626), for a rebuild (a new media URL or allowed types, or a first
 * index) or many new files, which reading at once could outlast a
 * request. Each run reads up to `BATCH` files; the index keeps what's
 * left, so the job needs no data of its own. The library queues it when
 * it falls behind, and Reindex when its own batch doesn't finish.
 */
final class MediaIndexJob extends Job
{
	/**
	 * How many media files one run reads.
	 */
	public const int BATCH = 250;

	public function __construct(private readonly MediaIndexer $indexer)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Index media files';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$report = $this->indexer->index(limit: self::BATCH);

		if ($report->pending > 0) {
			$all = $report->total + $report->pending;

			return JobResult::more([], intdiv(($all - $report->pending) * 100, max(1, $all)), sprintf('%d media files left to read.', $report->pending));
		}

		return JobResult::done(sprintf('Indexed %d media %s.', $report->total, $report->total === 1 ? 'file' : 'files'));
	}
}
