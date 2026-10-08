<?php

/**
 * Job store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * Where jobs are kept: the `jobs` storage area (D-485, D-621). Its writes
 * are many and short-lived, so it's the area likeliest to want a database
 * first; files (`FileJobStore`) are the default. Besides the jobs, it
 * keeps small named state (the scheduler's last runs, the runners'
 * times) and takes named locks.
 */
interface JobStore
{
	/**
	 * Saves a job, under its status.
	 *
	 * @throws JobException When it can't be written.
	 */
	public function save(JobRecord $job): void;

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
	 * Takes a queued job for one runner and saves it as running (with
	 * `$running`'s values), or returns `null` when another runner took it
	 * first. Two runners never get the same job.
	 *
	 * @throws JobException When it can't be written.
	 */
	public function claim(JobRecord $queued, JobRecord $running): ?JobRecord;

	/**
	 * Deletes a job.
	 */
	public function delete(string $id): void;

	/**
	 * Returns a named piece of state, or an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public function state(string $name): array;

	/**
	 * Saves a named piece of state.
	 *
	 * @param  array<string, mixed> $state
	 * @throws JobException When it can't be written.
	 */
	public function saveState(string $name, array $state): void;

	/**
	 * Runs a task while holding a named lock, unless someone else holds
	 * it, without waiting. Returns whether it ran.
	 *
	 * @param callable(): void $task
	 */
	public function locked(string $name, callable $task): bool;
}
