<?php

/**
 * Job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Throwable;

/**
 * Work done outside the request that asked for it (D-621): an import, a
 * transcript, a publish from the admin, pruning old files. A job is
 * registered by key, and queued with data, so what's stored never names
 * a class:
 *
 *     $container->get(JobRegistry::class)->register('acme/sync-orders', SyncOrders::class);
 *     $container->get(JobQueue::class)->push('acme/sync-orders', ['since' => $time]);
 *
 * Jobs are built through the container, so constructors can ask for
 * services. `handle()` does one chunk: work that might outlast a request
 * returns `JobResult::more()` with where it got to, and is queued again.
 */
abstract class Job
{
	/**
	 * Returns what the job does, for lists ("Reindex content").
	 */
	abstract public function label(): string;

	/**
	 * Does the work, or the next chunk of it, from `$job->data`.
	 *
	 * @throws Throwable When it fails in a way that trying again may fix.
	 */
	abstract public function handle(JobRecord $job): JobResult;

	/**
	 * Returns how many runs may throw before the job is failed.
	 */
	public function attempts(): int
	{
		return 3;
	}

	/**
	 * Returns how many seconds to wait before trying again, after the
	 * given number of attempts: one minute, then five, then 25.
	 */
	public function backoff(int $attempts): int
	{
		return 60 * 5 ** max(0, $attempts - 1);
	}
}
