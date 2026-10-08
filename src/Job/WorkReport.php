<?php

/**
 * Work report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * What a runner did in one go: the job runs, as each one ended (queued
 * again for more, done, or failed).
 */
final readonly class WorkReport
{
	/**
	 * @param list<JobRecord> $runs
	 */
	public function __construct(public array $runs = [])
	{}

	/**
	 * Returns how many runs ended with a status.
	 */
	public function count(?JobStatus $status = null): int
	{
		return $status === null
			? count($this->runs)
			: count(array_filter($this->runs, static fn (JobRecord $job): bool => $job->status === $status));
	}
}
