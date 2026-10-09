<?php

/**
 * Job controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Job\JobConfig;
use Blush\Job\JobException;
use Blush\Job\JobFactory;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobRunner;
use Blush\Job\RunnerKind;
use Blush\Job\Schedule;
use Blush\Job\Scheduler;
use Blush\Job\WebRunner;

/**
 * Background jobs in the admin (D-621): the Tools screen's Jobs tab, and
 * the admin as a runner for the jobs a person starts and waits on.
 *
 * - `GET {path}/api/jobs`, with `site.jobs`: the `jobs` (the newest
 *   `LIMIT`, newest first), the scheduled `tasks` (each job's key,
 *   `label`, `frequency` in words, and `last` and `next` runs), the
 *   `runners`' last runs, the `mode`, the `cron` line to add, and whether
 *   the server can run jobs after a page is served (`afterVisits`).
 * - `GET {path}/api/jobs/{id}`: one job.
 * - `POST {path}/api/jobs/{id}/run`: runs the job's next chunk now, when
 *   it's queued and due, and answers with it as it is; the admin asks
 *   again until it's finished, showing its progress.
 * - `POST {path}/api/jobs/{id}/retry` and `DELETE {path}/api/jobs/{id}`,
 *   with `site.jobs`: queue a failed job again, and delete one that isn't
 *   running.
 * - `POST {path}/api/jobs/schedule/{job}`, with `site.jobs`: queues a
 *   scheduled task now (Run Now), and answers with its job.
 *
 * A job's `result` is what a finished job hands back (D-624), such as a
 * Site Health fix's changes. A job someone queued is theirs to follow
 * and run without `site.jobs`.
 * Every time is ISO 8601.
 */
final readonly class JobController
{
	/**
	 * The most jobs listed.
	 */
	public const int LIMIT = 100;

	public function __construct(
		private JobQueue $queue,
		private JobRunner $runner,
		private JobFactory $jobs,
		private Schedule $schedule,
		private Scheduler $scheduler,
		private JobConfig $config,
		private Permissions $permissions,
		private AppConfig $app,
		private Paths $paths,
		private ClockInterface $clock
	) {}

	/**
	 * Lists the jobs, the schedule, and the runners.
	 */
	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = $this->account($request);

		if ($account === null || ! $this->permissions->can($account, Capability::SiteJobs)) {
			return $this->forbidden($account);
		}

		$tasks = [];

		foreach ($this->schedule->all() as $key => $task) {
			$tasks[] = [
				'job'       => $key,
				'label'     => $this->jobs->label($key),
				'frequency' => $task->frequency->describe(),
				'last'      => $this->format($this->scheduler->lastRun($key)),
				'next'      => $this->format($this->scheduler->nextRun($task))
			];
		}

		return self::json([
			'jobs'        => array_map($this->describe(...), array_slice(array_reverse($this->queue->all()), 0, self::LIMIT)),
			'tasks'       => $tasks,
			'runners'     => array_map(fn (?int $time): ?string => $time === null ? null : $this->format(DateTimeImmutable::createFromTimestamp($time)), $this->runner->lastRuns()),
			'mode'        => $this->config->runner->value,
			'cron'        => sprintf('* * * * * cd %s && php %s schedule:run > /dev/null 2>&1', escapeshellarg($this->paths->root), 'bin/' . Framework::BINARY),
			'afterVisits' => WebRunner::isAvailable()
		]);
	}

	/**
	 * Answers with one job.
	 */
	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = $this->account($request);
		$job     = $this->queue->find($id);

		if ($job === null) {
			return self::json(['error' => 'There\'s no such job.'], Status::NotFound);
		}

		if (! $this->canFollow($account, $job)) {
			return $this->forbidden($account);
		}

		return self::json(['job' => $this->describe($job)]);
	}

	/**
	 * Runs the job's next chunk, when it's queued and due, and answers
	 * with it as it is.
	 */
	public function run(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = $this->account($request);
		$job     = $this->queue->find($id);

		if ($job === null) {
			return self::json(['error' => 'There\'s no such job.'], Status::NotFound);
		}

		if (! $this->canFollow($account, $job)) {
			return $this->forbidden($account);
		}

		$this->runner->beat(RunnerKind::Admin);
		$this->runner->recover();

		$this->runner->run($this->queue->find($id) ?? $job);

		return self::json(['job' => $this->describe($this->queue->find($id) ?? $job)]);
	}

	/**
	 * Queues a failed job again.
	 */
	public function retry(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = $this->account($request);

		if ($account === null || ! $this->permissions->can($account, Capability::SiteJobs)) {
			return $this->forbidden($account);
		}

		$job = $this->queue->find($id);

		if ($job === null) {
			return self::json(['error' => 'There\'s no such job.'], Status::NotFound);
		}

		try {
			$queued = $this->queue->retry($job);
		} catch (JobException $e) {
			return self::json(['error' => $e->getMessage()], Status::InternalServerError);
		}

		return $queued === null
			? self::json(['error' => 'Only a failed job can be tried again.'], Status::Conflict)
			: self::json(['job' => $this->describe($queued)]);
	}

	/**
	 * Deletes a job that isn't running.
	 */
	public function delete(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = $this->account($request);

		if ($account === null || ! $this->permissions->can($account, Capability::SiteJobs)) {
			return $this->forbidden($account);
		}

		$job = $this->queue->find($id);

		if ($job === null) {
			return self::json(['error' => 'There\'s no such job.'], Status::NotFound);
		}

		return $this->queue->delete($job)
			? self::json(['deleted' => $job->id])
			: self::json(['error' => 'A running job can\'t be deleted. Wait for it to finish.'], Status::Conflict);
	}

	/**
	 * Queues a scheduled task now.
	 */
	public function schedule(ServerRequestInterface $request, string $job): ResponseInterface
	{
		$account = $this->account($request);

		if ($account === null || ! $this->permissions->can($account, Capability::SiteJobs)) {
			return $this->forbidden($account);
		}

		try {
			$record = $this->scheduler->runNow($job, $account->id);
		} catch (JobException $e) {
			return self::json(['error' => $e->getMessage()], Status::InternalServerError);
		}

		return $record === null
			? self::json(['error' => sprintf('"%s" isn\'t on the schedule.', $job)], Status::NotFound)
			: self::json(['job' => $this->describe($record)]);
	}

	/**
	 * Describes a job for the admin.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(JobRecord $job): array
	{
		$time = fn (?int $time): ?string => $time === null ? null : $this->format(DateTimeImmutable::createFromTimestamp($time));

		return [
			'id'        => $job->id,
			'job'       => $job->job,
			'label'     => $this->jobs->label($job->job),
			'status'    => $job->status->value,
			'attempts'  => $job->attempts,
			'queued'    => $time($job->queued),
			'available' => $time($job->available),
			'started'   => $time($job->started),
			'finished'  => $time($job->finished),
			'progress'  => $job->progress,
			'message'   => $job->message,
			'details'   => $job->details,
			'error'     => $job->error,
			'account'   => $job->account,
			'result'    => $job->result === [] ? (object) [] : $job->result,
			'due'       => $job->available <= $this->clock->now()->getTimestamp()
		];
	}

	/**
	 * Whether an account may follow and run a job: its own, or any with
	 * `site.jobs`.
	 */
	private function canFollow(?Account $account, JobRecord $job): bool
	{
		return $account !== null && ($job->account === $account->id || $this->permissions->can($account, Capability::SiteJobs));
	}

	/**
	 * Returns the signed-in account, or `null`.
	 */
	private function account(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : null;
	}

	/**
	 * Formats a time in the site's time zone, or returns `null`.
	 */
	private function format(?DateTimeInterface $time): ?string
	{
		return $time === null ? null : DateTimeImmutable::createFromInterface($time)->setTimezone($this->app->timezone())->format(DateTimeInterface::ATOM);
	}

	/**
	 * Answers that the account may not, or must sign in first.
	 */
	private function forbidden(?Account $account): ResponseInterface
	{
		return $account === null
			? self::json(['error' => 'Sign in first.'], Status::Unauthorized)
			: self::json(['error' => 'You aren\'t allowed to do that.'], Status::Forbidden);
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
