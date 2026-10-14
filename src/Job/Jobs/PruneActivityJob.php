<?php

/**
 * Prune activity job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Activity\ActivityLog;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Prunes the activity log (`ActivityLog::prune()`, D-758): changes kept
 * over 90 days are removed, and what changes over 30 days old replaced
 * (kept for Undo) is let go.
 */
final class PruneActivityJob extends Job
{
	public function __construct(private readonly ActivityLog $activity, private readonly Messages $messages)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('job.prune_activity_job.label', 'Prune the activity log');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		[$removed, $cleared] = $this->activity->prune();

		return JobResult::done($this->messages->t('job.prune_activity_job.removed', 'Removed {removed, plural, one {# old change} other {# old changes}}; let go of what {cleared} replaced.', removed: $removed, cleared: $cleared));
	}
}
