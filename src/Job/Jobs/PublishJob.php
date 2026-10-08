<?php

/**
 * Publish job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use Override;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Publish\PublishInProgress;
use Blush\Publish\Publisher;

/**
 * Puts content changes live, as `publish` and the webhook do (D-131),
 * for the admin's Publish (D-621). It isn't retried: a pull that failed
 * needs a person, and a publish already running is as good as this one.
 */
final class PublishJob extends Job
{
	public function __construct(private readonly Publisher $publisher)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Publish';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function attempts(): int
	{
		return 1;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		try {
			$report = $this->publisher->publish();
		} catch (PublishInProgress $e) {
			return JobResult::failed($e->getMessage());
		}

		if (! $report->isPublished()) {
			return JobResult::failed('Publishing stopped: the pull failed.', array_values(array_filter(explode("\n", $report->pull->output ?? ''))));
		}

		$failures = $report->index->failures ?? [];
		$details  = array_map(static fn (string $path, string $message): string => "{$path}: {$message}", array_keys($failures), $failures);

		return $failures === []
			? JobResult::done(sprintf('Published in %d ms.', $report->milliseconds))
			: JobResult::failed(sprintf('Published, but %d file(s) couldn\'t be indexed.', count($failures)), $details);
	}
}
