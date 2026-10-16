<?php

/**
 * List backups command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use Blush\Backup\Backup;
use Blush\Backup\BackupMaker;
use Blush\Backup\BackupParts;
use Blush\Backup\Backups;
use Blush\Backup\BackupStatus;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\AppConfig;
use Blush\Core\Paths;

/**
 * Lists the site's backups (D-885), newest first: each one's name (for
 * `backup:delete`), when, how it was made, what it holds, its size, and
 * its note, or a failure's reason. `--parts` lists the parts a backup can
 * hold instead, with what each holds now.
 */
#[Command('backup:list', 'List the site\'s backups.')]
final readonly class ListBackups
{
	public function __construct(
		private Backups $backups,
		private BackupParts $parts,
		private BackupMaker $maker,
		private AppConfig $app,
		private Paths $paths
	) {}

	public function __invoke(
		Output $output,
		#[Option('List the parts a backup can hold instead.')] bool $parts = false
	): ExitCode {
		if ($parts) {
			$measured = $this->maker->measure();
			$rows     = [];

			foreach ($this->parts->all() as $name => $part) {
				$rows[] = [$name, (string) $part->label, number_format($measured[$name]['count'] ?? 0), self::size($measured[$name]['bytes'] ?? 0), (string) $part->description];
			}

			$output->table(['Part', 'Label', 'Records', 'Size', 'Holds'], $rows);

			return ExitCode::Success;
		}

		$backups = $this->backups->all();

		if ($backups === []) {
			$output->line(sprintf('No backups in %s.', $this->paths->relative($this->backups->folder())));

			return ExitCode::Success;
		}

		$output->table(['Name', 'Made', 'How', 'Holds', 'Size', 'Note'], array_map(fn (Backup $backup): array => [
			$backup->name,
			DateTimeImmutable::createFromTimestamp($backup->created)->setTimezone($this->app->timezone())->format('Y-m-d H:i'),
			$backup->kind->value,
			count($backup->parts) === count($this->parts->all()) ? 'everything' : implode(', ', $backup->partNames()),
			match ($backup->status) {
				BackupStatus::Done    => self::size($backup->size),
				BackupStatus::Running => 'being made',
				BackupStatus::Failed  => 'failed'
			},
			$backup->status === BackupStatus::Failed ? (string) $backup->reason : $backup->note
		], $backups));

		return ExitCode::Success;
	}

	/**
	 * Returns bytes as a size people read.
	 */
	private static function size(int $bytes): string
	{
		return match (true) {
			$bytes >= 1_000_000_000 => sprintf('%.2f GB', $bytes / 1_000_000_000),
			$bytes >= 1_000_000     => sprintf('%.1f MB', $bytes / 1_000_000),
			default                 => sprintf('%d KB', max(1, (int) round($bytes / 1000)))
		};
	}
}
