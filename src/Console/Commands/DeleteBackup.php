<?php

/**
 * Delete backup command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Backup\BackupException;
use Blush\Backup\Backups;
use Blush\Backup\BackupStatus;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;

/**
 * Deletes a backup by its name (`backup:list`), or cancels the one being
 * made. A deleted backup waits a day in the backups folder's `.deleted`,
 * as one deleted in the admin does.
 */
#[Command('backup:delete', 'Delete one of the site\'s backups.')]
final readonly class DeleteBackup
{
	public function __construct(private Backups $backups)
	{}

	public function __invoke(
		Output $output,
		#[Argument('The backup\'s name, as `backup:list` shows it.')] string $name
	): ExitCode {
		$backup = $this->backups->find($name);

		if ($backup === null) {
			$output->error(sprintf('There\'s no backup named "%s".', $name), 'See their names with `backup:list`.');

			return ExitCode::Invalid;
		}

		try {
			$this->backups->delete($backup);
		} catch (BackupException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success($backup->status === BackupStatus::Running ? sprintf('Canceled %s.', $name) : sprintf('Deleted %s.', $name));

		return ExitCode::Success;
	}
}
