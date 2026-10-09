<?php

/**
 * Job queue.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * Puts work aside for a runner (D-621), and looks after what's waiting:
 *
 *     $queue->push('acme/transcribe', ['media' => $id], account: $account->id);
 *
 * - Data is ids and scalars (in arrays), never objects, so a job reads
 *   the same whichever runner picks it up, and a database could keep it.
 * - A `unique` key keeps a second copy out while one waits or runs: a
 *   reindex asked for by many saves is queued once. Pushing returns the
 *   copy already there.
 * - Capabilities are checked by whoever queues, before pushing; the job
 *   records who asked (`account`), and runs as the system.
 *
 * `StoredJobQueue` is the queue; a test binds `Testing\RecordingQueue`
 * in its place to see what was queued without running anything.
 */
interface JobQueue
{
	/**
	 * Queues a job and returns it, or the copy already waiting under the
	 * same `unique` key. `$delay` holds it back that many seconds.
	 *
	 * @param  array<string, mixed> $data
	 * @throws UnknownJob When nothing is registered under the key.
	 * @throws JobException When the data isn't plain values or the job
	 *                      can't be saved.
	 */
	public function push(string $job, array $data = [], ?string $account = null, ?string $unique = null, int $delay = 0): JobRecord;

	/**
	 * Returns a job, or `null` when there's none with the id.
	 */
	public function find(string $id): ?JobRecord;

	/**
	 * Returns the jobs with a status, or every job, oldest first.
	 *
	 * @return list<JobRecord>
	 */
	public function all(?JobStatus $status = null): array;

	/**
	 * Returns the job waiting or running under a unique key, or `null`.
	 */
	public function waiting(string $unique): ?JobRecord;

	/**
	 * Queues a failed job again, from where it got to, with its attempts
	 * reset. Returns it as queued, or `null` when it isn't failed.
	 *
	 * @throws JobException When it can't be saved.
	 */
	public function retry(JobRecord $job): ?JobRecord;

	/**
	 * Deletes a job that isn't running. Returns whether it did.
	 */
	public function delete(JobRecord $job): bool;

	/**
	 * Deletes done and failed jobs older than they're kept for
	 * (`JobConfig::$keepDone`, `$keepFailed`). Returns how many.
	 */
	public function prune(): int;
}
