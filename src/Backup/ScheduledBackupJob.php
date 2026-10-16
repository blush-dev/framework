<?php

/**
 * Scheduled backup job.
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
 * The schedule's backup (`core/scheduled-backup`, D-887), queued by the
 * scheduler when it's due: the first run starts a backup of the
 * schedule's parts (every part when it names none), each run after makes
 * a chunk of it, as `BackupJob` does, and the last removes the
 * schedule's backups past its Keep and tells whoever should hear
 * (`BackupNotices`). While a backup or restore runs, it's skipped, and
 * the schedule tries again at its next time.
 */
final class ScheduledBackupJob extends Job
{
	public function __construct(
		private readonly BackupMaker $maker,
		private readonly BackupParts $parts,
		private readonly Backups $backups,
		private readonly BackupNotices $notices,
		private readonly BackupConfig $config,
		private readonly Messages $messages
	) {}

	#[Override]
	public function label(): Text
	{
		return $this->messages->t('backup.scheduled_job.label', 'Back up the site on schedule');
	}

	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$name = is_string($job->data['name'] ?? null) ? $job->data['name'] : null;

		if ($name === null) {
			$schedule = $this->config->schedule;
			$parts    = array_values(array_intersect($schedule->parts ?? $this->parts->names(), $this->parts->names()));

			try {
				$name = $this->maker->prepare($parts === [] ? $this->parts->names() : $parts, kind: BackupKind::Scheduled)->name;
			} catch (BackupBusy) {
				return JobResult::done($this->messages->t('backup.scheduled_job.skipped', 'Skipped: a backup was being made or restored.'));
			} catch (BackupException $e) {
				$this->notices->failed(null, $e->getMessage());

				return JobResult::failed($e->getMessage());
			}
		}

		/** @var array<string, mixed> $state */
		$state = is_array($job->data['state'] ?? null) ? $job->data['state'] : [];

		try {
			$result = $this->maker->step($name, $state);
		} catch (BackupException $e) {
			$this->notices->failed($this->backups->find($name), $e->getMessage());

			return JobResult::failed($e->getMessage());
		}

		if ($result['state'] !== null) {
			return JobResult::more(['name' => $name, 'state' => $result['state']], $result['progress'], $result['message']);
		}

		$backup = $this->backups->find($name);

		if ($backup !== null) {
			$this->notices->done($backup);
		}

		$this->backups->prune($this->config->schedule->keep);

		return JobResult::done($result['message'], result: ['backup' => $name]);
	}

	#[Override]
	public function attempts(): int
	{
		return 1;
	}
}
