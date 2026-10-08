<?php

/**
 * Job type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Blush\Admin\HealthCheckJob;
use Blush\Admin\HealthFixJob;
use Blush\Job\Jobs\GoLiveJob;
use Blush\Job\Jobs\MediaIndexJob;
use Blush\Job\Jobs\PruneCacheJob;
use Blush\Job\Jobs\PruneJobsJob;
use Blush\Job\Jobs\PruneSessionsJob;
use Blush\Job\Jobs\PublishJob;
use Blush\Job\Jobs\ReindexJob;

/**
 * The framework's jobs, keyed by `blush/{name}` (D-019, D-621), and the
 * ones on the schedule by default.
 */
enum JobType: string
{
	case Publish       = 'blush/publish';
	case Reindex       = 'blush/reindex';
	case GoLive        = 'blush/go-live';
	case PruneCache    = 'blush/prune-cache';
	case PruneSessions = 'blush/prune-sessions';
	case PruneJobs     = 'blush/prune-jobs';
	case HealthFix     = 'blush/health-fix';
	case HealthCheck   = 'blush/health-check';
	case MediaIndex    = 'blush/media-index';

	/**
	 * Returns the job's class.
	 *
	 * @return class-string<Job>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Publish       => PublishJob::class,
			self::Reindex       => ReindexJob::class,
			self::GoLive        => GoLiveJob::class,
			self::PruneCache    => PruneCacheJob::class,
			self::PruneSessions => PruneSessionsJob::class,
			self::PruneJobs     => PruneJobsJob::class,
			self::HealthFix     => HealthFixJob::class,
			self::HealthCheck   => HealthCheckJob::class,
			self::MediaIndex    => MediaIndexJob::class
		};
	}

	/**
	 * Returns how often the job runs by default, or `null` for one that
	 * runs only when it's asked for.
	 */
	public function frequency(): ?Frequency
	{
		return match ($this) {
			self::GoLive        => Frequency::everyMinute(),
			self::PruneCache,
			self::PruneSessions => Frequency::hourly(),
			self::PruneJobs     => Frequency::daily('03:00'),
			default             => null
		};
	}
}
