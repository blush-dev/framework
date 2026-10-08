<?php

/**
 * Job runner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Throwable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs queued jobs (D-621). Every runner (cron's `schedule:run`, the
 * `jobs:work` worker, the admin, and the one after a page is served)
 * comes through here, so they behave alike:
 *
 * - A job is claimed before it runs (`JobStore::claim()`), so two
 *   runners never run one job, and runners need no lock between them.
 * - A run is one chunk. A job with more is queued again where it got
 *   to, at once, so `work()` carries on with it while time is left.
 * - A run that throws is tried again later (`Job::backoff()`), up to
 *   `Job::attempts()` times, then failed. A job left running longer
 *   than `JobConfig::$timeout` lost its runner (a fatal error, a killed
 *   process) and is treated as one that threw.
 * - A done job someone queued is kept (`JobConfig::$keepDone`) for its
 *   result; one the scheduler queued is deleted, since its last run is
 *   on the schedule.
 */
final readonly class JobRunner
{
	/**
	 * The state that keeps each runner's last run.
	 */
	public const string RUNNERS = 'runners';

	public function __construct(
		private JobStore $store,
		private JobFactory $factory,
		private JobConfig $config,
		private ClockInterface $clock,
		private LoggerInterface $logger
	) {}

	/**
	 * Works due jobs, oldest first, until none are left or `$seconds`
	 * have passed. `$accept` limits which jobs it takes. Records the
	 * runner's time first, so Site Health sees it even when there's
	 * nothing to do.
	 *
	 * @param ?callable(JobRecord): bool $accept
	 */
	public function work(RunnerKind $kind, float $seconds, ?callable $accept = null): WorkReport
	{
		$this->beat($kind);
		$this->recover();

		$start = hrtime(true);
		$runs  = [];

		while ((hrtime(true) - $start) / 1e9 < $seconds) {
			$next = $this->next($accept);

			if ($next === null) {
				break;
			}

			$run = $this->run($next);

			if ($run !== null) {
				$runs[] = $run;
			}
		}

		return new WorkReport($runs);
	}

	/**
	 * Runs one chunk of a queued job that's due, and returns the job as it
	 * ended. Returns `null` when the job isn't queued and due, or another
	 * runner claimed it first.
	 */
	public function run(JobRecord $job): ?JobRecord
	{
		$now = $this->clock->now()->getTimestamp();

		if ($job->status !== JobStatus::Queued || $job->available > $now) {
			return null;
		}

		try {
			$running = $this->store->claim($job, $job->with(['started' => $now, 'finished' => null]));
		} catch (JobException $e) {
			$this->logger->error('A job couldn\'t be claimed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

			return null;
		}

		if ($running === null) {
			return null;
		}

		try {
			$work = $this->factory->make($running->job);
		} catch (JobException $e) {
			return $this->end($running->with([
				'status'   => JobStatus::Failed,
				'finished' => $now,
				'error'    => $e->getMessage()
			]));
		}

		try {
			$result = $work->handle($running);
		} catch (Throwable $e) {
			return $this->end($this->thrown($running, $work, $e->getMessage()));
		}

		$finished = $this->clock->now()->getTimestamp();

		return $this->end(match ($result->status) {
			JobStatus::Queued => $running->with([
				'status'    => JobStatus::Queued,
				'data'      => $result->data,
				'available' => $finished,
				'progress'  => $result->progress ?? $running->progress,
				'message'   => $result->message === '' ? $running->message : $result->message,
				'details'   => $result->details
			]),
			default           => $running->with([
				'status'   => $result->status,
				'finished' => $finished,
				'progress' => $result->status === JobStatus::Done ? 100 : $running->progress,
				'message'  => $result->message,
				'details'  => $result->details,
				'error'    => $result->status === JobStatus::Done ? null : $result->message,
				'result'   => $result->result
			])
		});
	}

	/**
	 * Runs a job a chunk at a time until it's finished or waiting to try
	 * again, and returns it as it ended. For `sync` mode, where a job runs
	 * when it's queued.
	 */
	public function finish(JobRecord $job): JobRecord
	{
		while (($run = $this->run($job)) !== null) {
			$job = $run;
		}

		return $this->store->find($job->id) ?? $job;
	}

	/**
	 * Returns the oldest queued job that's due (and that `$accept` takes),
	 * or `null`.
	 *
	 * @param ?callable(JobRecord): bool $accept
	 */
	public function next(?callable $accept = null): ?JobRecord
	{
		$now = $this->clock->now()->getTimestamp();

		return array_find(
			$this->store->all(JobStatus::Queued),
			static fn (JobRecord $job): bool => $job->available <= $now && ($accept === null || $accept($job))
		);
	}

	/**
	 * Treats each job running longer than the timeout as one that threw:
	 * its runner stopped before it finished. Returns how many.
	 */
	public function recover(): int
	{
		$before    = $this->clock->now()->getTimestamp() - $this->config->timeout;
		$recovered = 0;

		foreach ($this->store->all(JobStatus::Running) as $job) {
			if (($job->started ?? 0) >= $before) {
				continue;
			}

			try {
				$work = $this->factory->make($job->job);
			} catch (JobException $e) {
				$this->end($job->with(['status' => JobStatus::Failed, 'finished' => $this->clock->now()->getTimestamp(), 'error' => $e->getMessage()]));
				$recovered++;

				continue;
			}

			$this->end($this->thrown($job, $work, sprintf('It stopped without finishing, after more than %d seconds: its runner may have run out of time.', $this->config->timeout)));
			$recovered++;
		}

		return $recovered;
	}

	/**
	 * Records a runner's time.
	 */
	public function beat(RunnerKind $kind): void
	{
		try {
			$this->store->saveState(self::RUNNERS, [...$this->store->state(self::RUNNERS), $kind->value => $this->clock->now()->getTimestamp()]);
		} catch (JobException $e) {
			$this->logger->warning('The job runner\'s time couldn\'t be recorded: {message}', ['message' => $e->getMessage()]);
		}
	}

	/**
	 * Returns each runner's last run, as a Unix time, or `null` for one
	 * that has never run.
	 *
	 * @return array<string, ?int>
	 */
	public function lastRuns(): array
	{
		$state = $this->store->state(self::RUNNERS);
		$runs  = [];

		foreach (RunnerKind::cases() as $kind) {
			$time = $state[$kind->value] ?? null;

			$runs[$kind->value] = is_int($time) ? $time : null;
		}

		return $runs;
	}

	/**
	 * Returns the last time cron or a worker ran, or `null` when neither
	 * ever has.
	 */
	public function lastDependableRun(): ?int
	{
		$runs = array_filter(
			$this->lastRuns(),
			static fn (?int $time, string $kind): bool => $time !== null && RunnerKind::from($kind)->isDependable(),
			ARRAY_FILTER_USE_BOTH
		);

		return $runs === [] ? null : max($runs);
	}

	/**
	 * Returns a job that threw (or stopped) as it's kept: queued to try
	 * again after its backoff, or failed once it's out of attempts.
	 */
	private function thrown(JobRecord $job, Job $work, string $error): JobRecord
	{
		$now      = $this->clock->now()->getTimestamp();
		$attempts = $job->attempts + 1;

		$this->logger->warning('The "{job}" job failed (attempt {attempt} of {attempts}): {error}', [
			'job'      => $job->job,
			'attempt'  => $attempts,
			'attempts' => $work->attempts(),
			'error'    => $error
		]);

		return $attempts < $work->attempts()
			? $job->with(['status' => JobStatus::Queued, 'attempts' => $attempts, 'available' => $now + $work->backoff($attempts), 'error' => $error])
			: $job->with(['status' => JobStatus::Failed, 'attempts' => $attempts, 'finished' => $now, 'error' => $error]);
	}

	/**
	 * Keeps a job as its run ended, or deletes it when it's a scheduled
	 * job that's done, and returns it.
	 */
	private function end(JobRecord $job): JobRecord
	{
		try {
			if ($job->status === JobStatus::Done && $job->account === null && str_starts_with($job->unique ?? '', Scheduler::UNIQUE)) {
				$this->store->delete($job->id);
			} else {
				$this->store->save($job);
			}
		} catch (JobException $e) {
			$this->logger->error('The "{job}" job couldn\'t be saved: {message}', ['job' => $job->job, 'message' => $e->getMessage(), 'exception' => $e]);
		}

		return $job;
	}
}
