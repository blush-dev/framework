<?php

/**
 * Refusing job fixture.
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
 * Fails in a way trying again won't fix.
 */
final class RefusingJob extends Job
{
	#[Override]
	public function label(): string
	{
		return 'Refuse';
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		return JobResult::failed('Nothing to do it with.', ['a detail']);
	}
}
