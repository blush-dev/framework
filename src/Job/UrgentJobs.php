<?php

/**
 * Urgent jobs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * Jobs this request queued that someone is waiting on (D-847), such as
 * a password link's email: they run right after the response is sent
 * (`WebRunner::afterResponse()`), in any runner mode, rather than when a
 * runner next comes by. Where the server can't finish a response first
 * (not PHP-FPM), nothing runs them early, and they wait for a runner
 * like any other job. One that fails stays queued, to be tried again.
 */
final class UrgentJobs
{
	/**
	 * The jobs' ids, in the order they were added.
	 *
	 * @var list<string>
	 */
	private array $ids = [];

	public function __construct(
		private readonly JobQueue $queue,
		private readonly JobRunner $runner
	) {}

	/**
	 * Marks a queued job to run after this response.
	 */
	public function add(JobRecord $job): void
	{
		if (! in_array($job->id, $this->ids, true)) {
			$this->ids[] = $job->id;
		}
	}

	/**
	 * Whether any job is waiting to run after this response.
	 */
	public function isEmpty(): bool
	{
		return $this->ids === [];
	}

	/**
	 * Runs each job to the end, and any marked while they run (a
	 * notification to a capability marks the jobs it queues). One that's
	 * already run, or is waiting to try again, is left alone.
	 */
	public function run(): void
	{
		while (($id = array_shift($this->ids)) !== null) {
			$job = $this->queue->find($id);

			if ($job !== null) {
				$this->runner->finish($job);
			}
		}
	}
}
