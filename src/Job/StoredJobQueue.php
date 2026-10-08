<?php

/**
 * Stored job queue.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Support\Uuid;

/**
 * The queue (D-621): jobs kept in the `JobStore` for a runner. In `sync`
 * mode (`RunnerMode::Sync`), a job runs when it's pushed.
 */
final readonly class StoredJobQueue implements JobQueue
{
	public function __construct(
		private JobStore $store,
		private JobRegistry $registry,
		private JobRunner $runner,
		private JobConfig $config,
		private ClockInterface $clock
	) {}

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

		if ($unique !== null) {
			$waiting = $this->waiting($unique);

			if ($waiting !== null) {
				return $waiting;
			}
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

		$this->store->save($record);

		return $this->config->runner === RunnerMode::Sync && $delay <= 0 ? $this->runner->finish($record) : $record;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(string $id): ?JobRecord
	{
		return $this->store->find($id);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(?JobStatus $status = null): array
	{
		return $this->store->all($status);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function waiting(string $unique): ?JobRecord
	{
		return array_find(
			[...$this->store->all(JobStatus::Running), ...$this->store->all(JobStatus::Queued)],
			static fn (JobRecord $job): bool => $job->unique === $unique
		);
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

		$queued = $job->with([
			'status'    => JobStatus::Queued,
			'attempts'  => 0,
			'available' => $this->clock->now()->getTimestamp(),
			'started'   => null,
			'finished'  => null,
			'error'     => null
		]);

		$this->store->save($queued);

		return $queued;
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

		$this->store->delete($job->id);

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prune(): int
	{
		$now    = $this->clock->now()->getTimestamp();
		$pruned = 0;

		$keep = [
			JobStatus::Done->value   => $this->config->keepDone,
			JobStatus::Failed->value => $this->config->keepFailed
		];

		foreach ([JobStatus::Done, JobStatus::Failed] as $status) {
			foreach ($this->store->all($status) as $job) {
				if (($job->finished ?? $job->queued) < $now - $keep[$status->value]) {
					$this->store->delete($job->id);
					$pruned++;
				}
			}
		}

		return $pruned;
	}
}
