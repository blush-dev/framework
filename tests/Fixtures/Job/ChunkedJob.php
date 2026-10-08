<?php

/**
 * Chunked job fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Job;

use Override;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * Counts to `total` one run at a time.
 */
final class ChunkedJob extends Job
{
	#[Override]
	public function label(): string
	{
		return 'Count';
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$done  = is_int($job->data['done'] ?? null) ? $job->data['done'] + 1 : 1;
		$total = is_int($job->data['total'] ?? null) ? $job->data['total'] : 1;

		return $done >= $total
			? JobResult::done("Counted to {$total}.")
			: JobResult::more(['done' => $done, 'total' => $total], intdiv($done * 100, $total), "Counted {$done} of {$total}.");
	}
}
