<?php

/**
 * Job result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * What one run of a job came to (D-621): it's done, it failed in a way
 * trying again won't fix, or there's more, a chunk at a time, since no
 * run may count on more time than a shared host's request gives. A job
 * with more hands back the data to start from next time (where it got
 * to) and how far along it is, and is queued again.
 *
 * A failure that trying again may fix (a timeout, a service that's down)
 * is thrown instead, and the job is retried.
 */
final readonly class JobResult
{
	/**
	 * @param list<string>         $details
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $result
	 */
	private function __construct(
		public JobStatus $status,
		public string $message,
		public array $details = [],
		public array $data = [],
		public ?int $progress = null,
		public array $result = []
	) {}

	/**
	 * The job is done. `$details` are lines worth showing under the
	 * message, and `$result` is plain data for whoever follows the job
	 * (the admin), such as what it changed.
	 *
	 * @param list<string>         $details
	 * @param array<string, mixed> $result
	 */
	public static function done(string $message = '', array $details = [], array $result = []): self
	{
		return new self(JobStatus::Done, $message, $details, progress: 100, result: $result);
	}

	/**
	 * The job ran but didn't work, and running it again as it is won't
	 * help, so it isn't retried.
	 *
	 * @param list<string>         $details
	 * @param array<string, mixed> $result
	 */
	public static function failed(string $message, array $details = [], array $result = []): self
	{
		return new self(JobStatus::Failed, $message, $details, result: $result);
	}

	/**
	 * There's more to do: the job is queued again with `$data`, and
	 * `$progress` (0 to 100) says how far along it is.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function more(array $data, ?int $progress = null, string $message = ''): self
	{
		return new self(JobStatus::Queued, $message, data: $data, progress: $progress === null ? null : max(0, min(99, $progress)));
	}
}
