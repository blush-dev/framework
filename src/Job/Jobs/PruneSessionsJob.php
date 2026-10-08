<?php

/**
 * Prune sessions job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Psr\Clock\ClockInterface;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Session\SessionConfig;
use Blush\Session\SessionStore;

/**
 * Removes admin sessions idle past `SessionConfig::$idle`.
 */
final class PruneSessionsJob extends Job
{
	public function __construct(
		private readonly SessionStore $sessions,
		private readonly SessionConfig $config,
		private readonly ClockInterface $clock
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Remove idle admin sessions';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$pruned = $this->sessions->prune($this->clock->now()->getTimestamp() - $this->config->idle);

		return JobResult::done(sprintf('Removed %d idle session%s.', $pruned, $pruned === 1 ? '' : 's'));
	}
}
