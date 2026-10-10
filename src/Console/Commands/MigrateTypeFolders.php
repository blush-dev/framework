<?php

/**
 * Migrate type folders command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Content\Type\TypeFolderMigration;
use Blush\Core\Framework;

/**
 * Lists the data types in `user/data/types` that still name their folder
 * (D-683), and with `--write` moves each one's files into `_` and its
 * name and writes it without the folder, keeping its addresses
 * (`TypeFolderMigration`, D-478). Without it, it fails when any is left.
 */
#[Command('content:type-folders', 'List data types that still name their folder, and move them into _ and their name.')]
final readonly class MigrateTypeFolders
{
	public function __construct(private TypeFolderMigration $migration)
	{}

	public function __invoke(
		Output $output,
		#[Option('Move each type\'s files into its folder and write it without the folder.')] bool $write = false
	): ExitCode {
		if ($write) {
			$steps = $output->steps();

			try {
				$done = $this->migration->migrate($steps(...));
			} finally {
				$steps->finish();
			}

			$output->status([
				...array_map(static fn (string $name, int $moved): StatusLine => new StatusLine(Outcome::Pass, 'moved', sprintf('%s (%s moved)', $name, Output::count($moved, 'file', 'files'))), array_keys($done['migrated']), array_values($done['migrated'])),
				...array_map(static fn (string $name, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$name}: {$message}"), array_keys($done['failed']), array_values($done['failed']))
			]);

			if ($done['failed'] !== []) {
				return ExitCode::Failure;
			}
		}

		$left = $this->migration->report();

		if ($left === []) {
			$output->success('No data type names its folder.');

			return ExitCode::Success;
		}

		$output->status(array_map(
			static fn (string $name, array $folders): StatusLine => new StatusLine(Outcome::Failure, 'folder', "{$name}: {$folders['from']}/ to {$folders['to']}/"),
			array_keys($left),
			array_values($left)
		));
		$output->error(
			Output::count(count($left), 'data type names its folder.', 'data types name their folders.'),
			'Move ' . (count($left) === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:type-folders --write`.'
		);

		return ExitCode::Failure;
	}
}
