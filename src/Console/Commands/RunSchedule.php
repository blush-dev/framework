<?php

/**
 * Schedule run command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Job\JobConfig;
use Blush\Job\JobFactory;
use Blush\Job\JobRunner;
use Blush\Job\JobStatus;
use Blush\Job\RunnerKind;
use Blush\Job\Scheduler;

/**
 * The cron entry (D-040, D-621): queues the scheduled tasks that are due,
 * then works the queue for `JobConfig::$budget` seconds (`--budget`
 * changes it), and records cron's time, so Site Health knows it's set
 * up. Run it every minute:
 *
 *     * * * * * cd /path/to/site && php bin/blush schedule:run > /dev/null 2>&1
 *
 * It fails when a job failed for good, so a cron that mails its errors
 * says so.
 */
#[Command('schedule:run', 'Queue the scheduled tasks that are due, then work the queue.')]
final readonly class RunSchedule
{
	public function __construct(
		private Scheduler $scheduler,
		private JobRunner $runner,
		private JobFactory $jobs,
		private JobConfig $config
	) {}

	public function __invoke(
		Output $output,
		#[Option('Seconds to work the queue for; defaults to the jobs config\'s budget.')] int $budget = 0
	): ExitCode {
		$queued = $this->scheduler->tick();
		$report = $this->runner->work(RunnerKind::Cron, $budget > 0 ? $budget : $this->config->budget);

		foreach ($report->runs as $run) {
			$output->line(sprintf('%s: %s. %s', $this->jobs->label($run->job), $run->status->label(), $run->error ?? $run->message), $run->status === JobStatus::Failed ? Verbosity::Normal : Verbosity::Verbose);
		}

		$output->success(sprintf(
			'Queued %d scheduled task%s; ran %d job%s: %d done, %d failed, %d to go on.',
			count($queued),
			count($queued) === 1 ? '' : 's',
			$report->count(),
			$report->count() === 1 ? '' : 's',
			$report->count(JobStatus::Done),
			$report->count(JobStatus::Failed),
			$report->count(JobStatus::Queued)
		), Verbosity::Verbose);

		return $report->count(JobStatus::Failed) > 0 ? ExitCode::Failure : ExitCode::Success;
	}
}
