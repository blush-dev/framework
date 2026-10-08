<?php

/**
 * Jobs retry command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Job\JobException;
use Blush\Job\JobQueue;
use Blush\Job\JobStatus;

/**
 * Queues failed jobs again (D-621): one by its id (from `jobs:list`), or
 * every failed job with `--all`. A runner picks them up as usual.
 */
#[Command('jobs:retry', 'Queue failed jobs again.')]
final readonly class RetryJobs
{
	public function __construct(private JobQueue $queue)
	{}

	/**
	 * @throws JobException When a job can't be saved.
	 */
	public function __invoke(
		Output $output,
		#[Argument('The failed job\'s id.')] ?string $id = null,
		#[Option('Retry every failed job.')] bool $all = false
	): ExitCode {
		if (($id === null) === ! $all) {
			$output->error('Give a failed job\'s id, or --all.');

			return ExitCode::Invalid;
		}

		$jobs = $all ? $this->queue->all(JobStatus::Failed) : array_filter([$this->queue->find($id ?? '')]);

		if (! $all && $jobs === []) {
			$output->error(sprintf('There\'s no job "%s".', $id));

			return ExitCode::Failure;
		}

		$retried = 0;

		foreach ($jobs as $job) {
			if ($this->queue->retry($job) !== null) {
				$retried++;
			} else {
				$output->warning(sprintf('Job %s isn\'t failed; it\'s %s.', $job->id, $job->status->value));
			}
		}

		$output->success(sprintf('Queued %d job%s again.', $retried, $retried === 1 ? '' : 's'));

		return ExitCode::Success;
	}
}
