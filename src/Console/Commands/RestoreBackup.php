<?php

/**
 * Restore backup command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Backup\BackupException;
use Blush\Backup\BackupGroup;
use Blush\Backup\BackupParts;
use Blush\Backup\BackupRestorer;
use Blush\Backup\Backups;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Translation\Text;

/**
 * Restores parts of a backup by its name (D-886): every part it holds but
 * the Users ones (accounts, roles, and access), unless `--parts` names
 * them, as the admin's restore starts. It says what refuses it, or what
 * it will do, and asks first unless `--yes`. The site is backed up as it
 * is first, and the command says which backup to restore to undo it.
 */
#[Command('backup:restore', 'Restore parts of one of the site\'s backups.')]
final readonly class RestoreBackup
{
	public function __construct(
		private Backups $backups,
		private BackupParts $parts,
		private BackupRestorer $restorer
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The backup\'s name, as `backup:list` shows it.')] string $name,
		#[Option('The parts to restore, separated by commas; every part it holds but accounts, roles, and access when left out.')] ?string $parts = null,
		#[Option('Restore without asking.')] bool $yes = false
	): ExitCode {
		$backup = $this->backups->find($name);

		if ($backup === null) {
			$output->error(sprintf('There\'s no backup named "%s".', $name), 'See their names with `backup:list`.');

			return ExitCode::Invalid;
		}

		$names = $parts === null
			? array_values(array_filter($backup->partNames(), fn (string $part): bool => $this->parts->get($part)?->group !== BackupGroup::Users))
			: array_values(array_filter(array_map(trim(...), explode(',', $parts))));

		$check = $this->restorer->check($backup, $names);

		foreach ($check->problems as $problem) {
			$output->error((string) $problem);
		}

		if (! $check->allowed()) {
			return ExitCode::Failure;
		}

		foreach ($check->warnings as $warning) {
			$output->warning((string) $warning);
		}

		if (! $yes && ! $prompt->confirm(sprintf('Restore %s from %s? The site is backed up first.', implode(', ', $names), $name))) {
			$output->line('Nothing was restored.');

			return ExitCode::Failure;
		}

		$steps = $output->steps();

		try {
			$safety = $this->restorer->restore($backup, $names, step: static function (Text $message, int $progress) use ($steps): void {
				$steps((string) $message, $progress, 100);
			});
			$steps->finish();
		} catch (BackupException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Restored %s from %s.', implode(', ', $names), $name));
		$output->next(sprintf('To undo it, restore the backup made first: `backup:restore %s --parts=%s`.', $safety->name, implode(',', $names)));

		return ExitCode::Success;
	}
}
