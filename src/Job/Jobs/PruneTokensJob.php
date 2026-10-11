<?php

/**
 * Prune tokens job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Auth\Tokens;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

/**
 * Removes API tokens that expired over 30 days ago (`Tokens::prune()`,
 * D-748). Until then, an expired token stays listed, so its account
 * can see why something stopped working.
 */
final class PruneTokensJob extends Job
{
	public function __construct(private readonly Tokens $tokens)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Remove long-expired API tokens';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$pruned = $this->tokens->prune();

		return JobResult::done(sprintf('Removed %d expired token%s.', $pruned, $pruned === 1 ? '' : 's'));
	}
}
