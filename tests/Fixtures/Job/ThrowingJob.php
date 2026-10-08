<?php

/**
 * Throwing job fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Job;

use Override;
use RuntimeException;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * Throws every time, and gives up after two attempts.
 */
final class ThrowingJob extends Job
{
	#[Override]
	public function label(): string
	{
		return 'Call a service';
	}

	#[Override]
	public function attempts(): int
	{
		return 2;
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		throw new RuntimeException('The service is down.');
	}
}
