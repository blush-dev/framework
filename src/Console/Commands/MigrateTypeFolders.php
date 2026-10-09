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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\Type\TypeFolderMigration;

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
			$done = $this->migration->migrate();

			foreach ($done['migrated'] as $name => $moved) {
				$output->line(sprintf('%s  %s (%d moved)', $output->style('moved ', Style::Green), $name, $moved));
			}

			foreach ($done['failed'] as $name => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed', Style::Red), $name, $message));
			}

			if ($done['failed'] !== []) {
				return ExitCode::Failure;
			}
		}

		$left = $this->migration->report();

		if ($left === []) {
			$output->success('No data type names its folder.');

			return ExitCode::Success;
		}

		foreach ($left as $name => $folders) {
			$output->line(sprintf('%s  %s: %s/ to %s/', $output->style('folder', Style::Red), $name, $folders['from'], $folders['to']));
		}

		$output->error(sprintf('%d %s; move %s with --write.', count($left), count($left) === 1 ? 'data type names its folder' : 'data types name their folders', count($left) === 1 ? 'it' : 'them'));

		return ExitCode::Failure;
	}
}
