<?php

/**
 * Prune jobs job.
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
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * Removes finished jobs kept past `JobConfig::$keepDone` and
 * `$keepFailed`.
 */
final class PruneJobsJob extends Job
{
	public function __construct(private readonly JobQueue $queue)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Remove old finished jobs';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$pruned = $this->queue->prune();

		return JobResult::done(sprintf('Removed %d finished job%s.', $pruned, $pruned === 1 ? '' : 's'));
	}
}
