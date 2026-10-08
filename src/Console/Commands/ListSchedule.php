<?php

/**
 * Schedule list command.
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
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\AppConfig;
use Blush\Job\JobFactory;
use Blush\Job\JobRunner;
use Blush\Job\RunnerKind;
use Blush\Job\Schedule;
use Blush\Job\Scheduler;

/**
 * Lists the scheduled tasks (D-621): each job, how often it runs, and
 * when it last ran and runs next, in the site's time zone; then when
 * each runner last came by.
 */
#[Command('schedule:list', 'List the scheduled tasks and when they run.')]
final readonly class ListSchedule
{
	public function __construct(
		private Schedule $schedule,
		private Scheduler $scheduler,
		private JobRunner $runner,
		private JobFactory $jobs,
		private AppConfig $app
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$rows = [];

		foreach ($this->schedule->all() as $key => $task) {
			$rows[] = [
				$key,
				$this->jobs->label($key),
				$task->frequency->describe(),
				$this->scheduler->lastRun($key)?->format('Y-m-d H:i') ?? 'never',
				$this->scheduler->nextRun($task)?->format('Y-m-d H:i') ?? 'never'
			];
		}

		if ($rows === []) {
			$output->line('Nothing is scheduled.');
		} else {
			$output->table(['Job', 'Label', 'Runs', 'Last run', 'Next run'], $rows);
		}

		$output->newLine();

		foreach ($this->runner->lastRuns() as $kind => $time) {
			$output->line(sprintf('%s: %s', RunnerKind::from($kind)->label(), $time === null ? 'never' : DateTimeImmutable::createFromTimestamp($time)->setTimezone($this->app->timezone())->format('Y-m-d H:i')));
		}

		return ExitCode::Success;
	}
}
