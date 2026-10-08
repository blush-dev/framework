<?php

/**
 * Scheduler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Blush\Core\AppConfig;

/**
 * Queues scheduled tasks when they're due (D-621). A task is due when
 * its frequency's first time after its last run has come, so a run
 * missed (no cron, a quiet site) happens once when a runner next comes
 * by, not once for every time missed. A task that has never run is due
 * at once. Times are read in the site's time zone.
 *
 * Each task is queued under a unique key, so a run still waiting isn't
 * queued twice, and ticks take a lock, so two runners never queue the
 * same tasks at once.
 */
final readonly class Scheduler
{
	/**
	 * The start of a scheduled job's unique key.
	 */
	public const string UNIQUE = 'schedule:';

	/**
	 * The state that keeps each task's last run.
	 */
	private const string STATE = 'schedule';

	public function __construct(
		private Schedule $schedule,
		private JobQueue $queue,
		private JobStore $store,
		private AppConfig $app,
		private ClockInterface $clock,
		private LoggerInterface $logger
	) {}

	/**
	 * Queues every task that's due, and returns the jobs queued. Returns
	 * none when another runner is ticking.
	 *
	 * @return list<JobRecord>
	 * @phpstan-impure
	 */
	public function tick(): array
	{
		$queued = [];

		$this->store->locked(self::STATE, function () use (&$queued): void {
			$now  = $this->now();
			$last = $this->store->state(self::STATE);

			foreach ($this->schedule->all() as $key => $task) {
				if (! $this->isDue($task, $now)) {
					continue;
				}

				try {
					$queued[]   = $this->queue->push($key, $task->data, unique: self::UNIQUE . $key);
					$last[$key] = $now->getTimestamp();
				} catch (JobException $e) {
					$this->logger->warning('The scheduled "{job}" job couldn\'t be queued: {message}', ['job' => $key, 'message' => $e->getMessage()]);
				}
			}

			try {
				$this->store->saveState(self::STATE, $last);
			} catch (JobException $e) {
				$this->logger->error('The schedule\'s last runs couldn\'t be saved: {message}', ['message' => $e->getMessage()]);
			}
		});

		return $queued;
	}

	/**
	 * Queues a scheduled task now, whether it's due or not (Run Now), and
	 * returns its job, or `null` when it isn't on the schedule.
	 *
	 * @throws JobException
	 */
	public function runNow(string $job, ?string $account = null): ?JobRecord
	{
		$task = $this->schedule->get($job);

		if ($task === null) {
			return null;
		}

		$record = $this->queue->push($job, $task->data, $account, self::UNIQUE . $job);

		$this->store->locked(self::STATE, function () use ($job): void {
			$this->store->saveState(self::STATE, [...$this->store->state(self::STATE), $job => $this->now()->getTimestamp()]);
		});

		return $record;
	}

	/**
	 * Whether a task is due.
	 */
	public function isDue(ScheduledTask $task, DateTimeImmutable $now): bool
	{
		$last = $this->lastRun($task->job);

		if ($last === null) {
			return true;
		}

		try {
			return $task->frequency->next($last) <= $now;
		} catch (InvalidFrequency) {
			return false;
		}
	}

	/**
	 * Returns when a task was last queued, or `null` when it never has.
	 */
	public function lastRun(string $job): ?DateTimeImmutable
	{
		$time = $this->store->state(self::STATE)[$job] ?? null;

		return is_int($time) ? DateTimeImmutable::createFromTimestamp($time)->setTimezone($this->app->timezone()) : null;
	}

	/**
	 * Returns when a task is next due: now for one that has never run or
	 * is overdue, else its frequency's next time after its last run.
	 * Returns `null` for a frequency that never comes.
	 */
	public function nextRun(ScheduledTask $task): ?DateTimeImmutable
	{
		$now  = $this->now();
		$last = $this->lastRun($task->job);

		try {
			$next = $task->frequency->next($last ?? $now->modify('-1 minute'));
		} catch (InvalidFrequency) {
			return null;
		}

		return $last === null || $next < $now ? $now : $next;
	}

	/**
	 * Returns the current time in the site's time zone.
	 */
	private function now(): DateTimeImmutable
	{
		return DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());
	}
}
