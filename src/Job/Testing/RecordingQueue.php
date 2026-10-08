<?php

/**
 * Recording queue.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Testing;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Job\JobException;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobRegistry;
use Blush\Job\JobStatus;
use Blush\Job\UnknownJob;
use Blush\Support\Uuid;

/**
 * A queue for tests (D-621): it keeps what's pushed in memory and never
 * runs it, so a test can check what its code queued. Bind it in place of
 * the queue before the code under test asks for one:
 *
 *     $queue = new RecordingQueue($container->get(JobRegistry::class), $container->get(ClockInterface::class));
 *     $container->instance(JobQueue::class, $queue);
 *
 *     // … run the code …
 *
 *     $this->assertCount(1, $queue->pushed('acme/sync-orders'));
 *
 * It checks what the real queue checks (the job is registered, its data
 * is plain values) and keeps `unique` keys the same way, so a test fails
 * where the site would.
 */
final class RecordingQueue implements JobQueue
{
	/**
	 * The jobs, by id, in the order they were pushed.
	 *
	 * @var array<string, JobRecord>
	 */
	private array $jobs = [];

	/**
	 * Every job pushed, copies `unique` turned away included, in order.
	 *
	 * @var list<JobRecord>
	 */
	private array $pushes = [];

	public function __construct(
		private readonly JobRegistry $registry,
		private readonly ClockInterface $clock
	) {}

	/**
	 * Returns the jobs pushed (with a key, or all of them), in order. A
	 * push a `unique` key turned away isn't one.
	 *
	 * @return list<JobRecord>
	 */
	public function pushed(?string $job = null): array
	{
		return array_values(array_filter($this->pushes, static fn (JobRecord $record): bool => $job === null || $record->job === $job));
	}

	/**
	 * Forgets everything pushed.
	 */
	public function clear(): void
	{
		$this->jobs   = [];
		$this->pushes = [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function push(string $job, array $data = [], ?string $account = null, ?string $unique = null, int $delay = 0): JobRecord
	{
		if (! $this->registry->isRegistered($job)) {
			throw UnknownJob::named($job);
		}

		if (! JobRecord::isPlain($data)) {
			throw new JobException(sprintf('The "%s" job\'s data must be ids and plain values (strings, numbers, booleans, null, and arrays of them).', $job));
		}

		$waiting = $unique === null ? null : $this->waiting($unique);

		if ($waiting !== null) {
			return $waiting;
		}

		$now    = $this->clock->now();
		$record = new JobRecord(
			id: Uuid::v7($now),
			job: $job,
			data: $data,
			queued: $now->getTimestamp(),
			available: $now->getTimestamp() + max(0, $delay),
			account: $account,
			unique: $unique
		);

		$this->jobs[$record->id] = $record;
		$this->pushes[]          = $record;

		return $record;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?JobRecord
	{
		return $this->jobs[$id] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(?JobStatus $status = null): array
	{
		return array_values(array_filter($this->jobs, static fn (JobRecord $job): bool => $status === null || $job->status === $status));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function waiting(string $unique): ?JobRecord
	{
		return array_find($this->jobs, static fn (JobRecord $job): bool => $job->unique === $unique && ! $job->status->isFinished());
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function retry(JobRecord $job): ?JobRecord
	{
		if ($job->status !== JobStatus::Failed) {
			return null;
		}

		return $this->jobs[$job->id] = $job->with([
			'status'    => JobStatus::Queued,
			'attempts'  => 0,
			'available' => $this->clock->now()->getTimestamp(),
			'started'   => null,
			'finished'  => null,
			'error'     => null
		]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(JobRecord $job): bool
	{
		if ($job->status === JobStatus::Running) {
			return false;
		}

		unset($this->jobs[$job->id]);

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(): int
	{
		$finished = array_filter($this->jobs, static fn (JobRecord $job): bool => $job->status->isFinished());

		$this->jobs = array_diff_key($this->jobs, $finished);

		return count($finished);
	}
}
