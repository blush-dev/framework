<?php

/**
 * Jobs work command.
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
use Blush\Job\RunnerKind;
use Blush\Job\Scheduler;

/**
 * A worker that keeps going (D-621), for a server that can keep a
 * process running (systemd, Supervisor): it ticks the scheduler and
 * works the queue, resting `--sleep` seconds when there's nothing to do,
 * until `--max-time` seconds have passed (none by default) or, with
 * `--stop-when-empty`, the queue is empty. Cron isn't needed beside it.
 * Restart it after deploying new code, as it keeps the code it started
 * with.
 */
#[Command('jobs:work', 'Work the queue and the schedule until stopped.')]
final readonly class WorkJobs
{
	public function __construct(
		private Scheduler $scheduler,
		private JobRunner $runner,
		private JobFactory $jobs,
		private JobConfig $config
	) {}

	public function __invoke(
		Output $output,
		#[Option('Seconds to rest when there\'s nothing to do.')] int $sleep = 3,
		#[Option('Stop after this many seconds; 0 for never.')] int $maxTime = 0,
		#[Option('Stop once the queue is empty.')] bool $stopWhenEmpty = false
	): ExitCode {
		$start = time();
		$ran   = 0;

		while ($maxTime <= 0 || time() - $start < $maxTime) {
			$this->scheduler->tick();

			$report = $this->runner->work(RunnerKind::Worker, $this->config->budget);

			foreach ($report->runs as $run) {
				$output->line(sprintf('%s %s: %s. %s', date('H:i:s'), $this->jobs->label($run->job), $run->status->label(), $run->error ?? $run->message), Verbosity::Verbose);
			}

			$ran += $report->count();

			if ($report->count() === 0) {
				if ($stopWhenEmpty) {
					break;
				}

				sleep(max(1, $sleep));
			}
		}

		$output->success(sprintf('Ran %d job%s.', $ran, $ran === 1 ? '' : 's'));

		return ExitCode::Success;
	}
}
