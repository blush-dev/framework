<?php

/**
 * Make backup command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Backup\BackupException;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupParts;
use Blush\Backup\Backups;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\Paths;
use Blush\Translation\Text;

/**
 * Backs up the site (D-886), every part unless `--parts` names some, with
 * an optional `--note`, and says where the archive is. It's made here, start
 * to end, not as a job.
 */
#[Command('backup:make', 'Back up the site\'s stored data.')]
final readonly class MakeBackup
{
	public function __construct(
		private BackupMaker $maker,
		private BackupParts $parts,
		private Backups $backups,
		private Paths $paths
	) {}

	public function __invoke(
		Output $output,
		#[Option('The parts to back up, separated by commas (see `backup:list --parts`); every part when left out.')] ?string $parts = null,
		#[Option('A note to keep with it, up to 60 characters.')] string $note = ''
	): ExitCode {
		$names = $parts === null ? $this->parts->names() : array_values(array_filter(array_map(trim(...), explode(',', $parts))));
		$steps = $output->steps();

		try {
			$backup = $this->maker->make($names, $note, step: static function (Text $message, int $progress) use ($steps): void {
				$steps((string) $message, $progress, 100);
			});
			$steps->finish();
		} catch (BackupException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$archive = $this->backups->archive($backup);

		$output->success(sprintf('Backed up %s to %s.', implode(', ', $backup->partNames()), $archive === null ? $backup->name : $this->paths->relative($archive)));
		$output->next('It holds password hashes: keep any copy you download somewhere private.');

		return ExitCode::Success;
	}
}
