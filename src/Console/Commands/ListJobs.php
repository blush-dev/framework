<?php

/**
 * Jobs list command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\AppConfig;
use Blush\Job\JobFactory;
use Blush\Job\JobQueue;
use Blush\Job\JobStatus;

/**
 * Lists the jobs (D-621), newest first: their ids (for `jobs:retry`),
 * what they are, where they are, when they were queued and by whom, and
 * what they last said or failed with. `--status` picks one status.
 */
#[Command('jobs:list', 'List the background jobs.')]
final readonly class ListJobs
{
	public function __construct(
		private JobQueue $queue,
		private JobFactory $jobs,
		private AppConfig $app
	) {}

	public function __invoke(
		Output $output,
		#[Option('Only jobs with this status: queued, running, done, or failed.')] ?string $status = null
	): ExitCode {
		$filter = $status === null ? null : JobStatus::tryFrom($status);

		if ($status !== null && $filter === null) {
			$output->error(sprintf('There\'s no "%s" status; use queued, running, done, or failed.', $status));

			return ExitCode::Invalid;
		}

		$jobs = array_reverse($this->queue->all($filter));

		if ($jobs === []) {
			$output->line($filter === null ? 'No jobs.' : sprintf('No %s jobs.', $filter->value));

			return ExitCode::Success;
		}

		$rows = [];

		foreach ($jobs as $job) {
			$rows[] = [
				$job->id,
				$this->jobs->label($job->job),
				$job->status->label() . ($job->progress !== null && $job->status === JobStatus::Queued ? " ({$job->progress}%)" : ''),
				DateTimeImmutable::createFromTimestamp($job->queued)->setTimezone($this->app->timezone())->format('Y-m-d H:i'),
				$job->account ?? 'system',
				$job->error ?? $job->message
			];
		}

		$output->table(['Id', 'Job', 'Status', 'Queued', 'By', 'Message'], $rows);

		return ExitCode::Success;
	}
}
