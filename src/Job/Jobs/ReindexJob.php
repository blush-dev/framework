<?php

/**
 * Reindex job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Media\Index\MediaIndexer;

/**
 * Brings the content index up to date with the files, as `content:index`
 * does, then the media index, as `media:index` does (D-288), for the
 * admin's Reindex (D-621). When anything changed, the content version
 * moves on, so cached pages that showed the old content aren't served
 * again.
 */
final class ReindexJob extends Job
{
	public function __construct(
		private readonly Indexer $indexer,
		private readonly MediaIndexer $media,
		private readonly ContentVersion $version
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Reindex content';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$report = $this->indexer->index();
		$media  = $this->media->index();

		if ($report->written) {
			$this->version->bump();
		}

		$summary = sprintf(
			'Indexed %d %s: %d added, %d changed, %d removed.',
			$report->total,
			$report->total === 1 ? 'entry' : 'entries',
			count($report->added),
			count($report->changed),
			count($report->removed)
		) . sprintf(' Indexed %d media %s.', $media->total, $media->total === 1 ? 'file' : 'files');

		if ($report->failures === []) {
			return JobResult::done($summary);
		}

		return JobResult::failed(
			sprintf('%s %d file(s) couldn\'t be indexed.', $summary, count($report->failures)),
			array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($report->failures), $report->failures)
		);
	}
}
