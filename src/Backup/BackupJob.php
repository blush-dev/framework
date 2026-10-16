<?php

/**
 * Backup job.
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
 * Makes a backup in the background (`core/backup`, D-886), a chunk a
 * run, with `BackupMaker`. It isn't tried again: a failure is the
 * backup's, said on its row, and a new backup is a new try.
 */
final class BackupJob extends Job
{
	public function __construct(
		private readonly BackupMaker $maker,
		private readonly Messages $messages
	) {}

	#[Override]
	public function label(): Text
	{
		return $this->messages->t('backup.job.label', 'Back up the site');
	}

	#[Override]
	public function describe(JobRecord $job): string
	{
		$name = $job->data['name'] ?? null;

		return is_string($name)
			? (string) $this->messages->t('backup.job.describe', 'Back up the site ({name})', name: $name)
			: (string) $this->label();
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$name = $job->data['name'] ?? null;

		if (! is_string($name)) {
			return JobResult::failed($this->messages->t('backup.job.no_name', 'The job doesn\'t say which backup to make.'));
		}

		$state = is_array($job->data['state'] ?? null) ? $job->data['state'] : [];

		try {
			/** @var array<string, mixed> $state */
			$result = $this->maker->step($name, $state);
		} catch (BackupException $e) {
			return JobResult::failed($e->getMessage());
		}

		return $result['state'] === null
			? JobResult::done($result['message'], result: ['backup' => $name])
			: JobResult::more(['name' => $name, 'state' => $result['state']], $result['progress'], $result['message']);
	}

	#[Override]
	public function attempts(): int
	{
		return 1;
	}
}
