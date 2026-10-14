<?php

/**
 * Prune cache job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Cache\Caches;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Removes expired entries from the cache store.
 */
final class PruneCacheJob extends Job
{
	public function __construct(private readonly Caches $caches, private readonly Messages $messages)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('job.prune_cache_job.label', 'Remove expired cache entries');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$pruned = $this->caches->prune();

		return JobResult::done($this->messages->t('job.prune_cache_job.removed', 'Removed {count, plural, one {# expired cache entry} other {# expired cache entries}}.', count: $pruned));
	}
}
