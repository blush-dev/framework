<?php

/**
 * Schedule.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * The scheduled tasks, by job key (D-621). Scheduled tasks are jobs on a
 * timetable: when one is due, the scheduler queues its job, so one runner
 * does everything and one list shows it. Core seeds its own; an
 * extension adds its own in its provider's `boot()`, for a job it has
 * registered:
 *
 *     $container->get(Schedule::class)->add('acme/sync-orders', Frequency::hourly());
 *
 * A job has one place on the schedule; adding it again replaces it.
 */
final class Schedule
{
	/**
	 * The tasks, by job key.
	 *
	 * @var array<string, ScheduledTask>
	 */
	private array $tasks = [];

	/**
	 * Schedules a job.
	 *
	 * @param array<string, mixed> $data
	 */
	public function add(string $job, Frequency $frequency, array $data = []): void
	{
		$this->tasks[$job] = new ScheduledTask($job, $frequency, $data);
	}

	/**
	 * Schedules a job unless it's scheduled already, so core's tasks
	 * never replace an extension's.
	 *
	 * @param array<string, mixed> $data
	 */
	public function addIf(string $job, Frequency $frequency, array $data = []): void
	{
		if (! isset($this->tasks[$job])) {
			$this->add($job, $frequency, $data);
		}
	}

	/**
	 * Takes a job off the schedule.
	 */
	public function remove(string $job): void
	{
		unset($this->tasks[$job]);
	}

	/**
	 * Returns a job's task, or `null` when it isn't scheduled.
	 */
	public function get(string $job): ?ScheduledTask
	{
		return $this->tasks[$job] ?? null;
	}

	/**
	 * Returns every task, by job key, in the order they were added.
	 *
	 * @return array<string, ScheduledTask>
	 */
	public function all(): array
	{
		return $this->tasks;
	}
}
