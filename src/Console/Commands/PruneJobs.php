<?php

/**
 * Jobs prune command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Job\JobQueue;

/**
 * Removes finished jobs kept past `JobConfig::$keepDone` and
 * `$keepFailed` (D-621), as the scheduled `blush/prune-jobs` does daily.
 */
#[Command('jobs:prune', 'Remove old finished jobs.')]
final readonly class PruneJobs
{
	public function __construct(private JobQueue $queue)
	{}

	public function __invoke(Output $output): ExitCode
	{
		$pruned = $this->queue->prune();

		$output->success(sprintf('Removed %d finished job%s.', $pruned, $pruned === 1 ? '' : 's'));

		return ExitCode::Success;
	}
}
