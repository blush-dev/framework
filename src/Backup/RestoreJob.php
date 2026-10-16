<?php

/**
 * Restore job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Override;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Restores parts of a backup in the background (`core/restore`, D-886),
 * a step a run, with `BackupRestorer`: the site backed up first, then the
 * backup unzipped, each table replaced, and the site brought up to date.
 * It isn't tried again: a restore that failed partway is undone by
 * restoring the backup it made first, which its failure names.
 */
final class RestoreJob extends Job
{
	public function __construct(
		private readonly BackupRestorer $restorer,
		private readonly Messages $messages
	) {}

	#[Override]
	public function label(): Text
	{
		return $this->messages->t('backup.restore_job.label', 'Restore a backup');
	}

	#[Override]
	public function describe(JobRecord $job): string
	{
		$name = $job->data['backup'] ?? null;

		return is_string($name)
			? (string) $this->messages->t('backup.restore_job.describe', 'Restore a backup ({name})', name: $name)
			: (string) $this->label();
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		try {
			$result = $this->restorer->step($job->data);
		} catch (BackupException $e) {
			$safety = $job->data['safety'] ?? null;

			return JobResult::failed(
				is_string($safety) && ($job->data['phase'] ?? 'safety') !== 'safety'
					? $this->messages->t('backup.restore_job.failed_partway', 'The restore stopped partway: {reason} To put the site back as it was, restore {safety}.', reason: $e->getMessage(), safety: $safety)
					: $e->getMessage(),
				result: ['safety' => is_string($safety) ? $safety : null]
			);
		}

		return $result['data'] === null
			? JobResult::done($result['message'], result: $result['result'])
			: JobResult::more($result['data'], $result['progress'], $result['message']);
	}

	#[Override]
	public function attempts(): int
	{
		return 1;
	}
}
