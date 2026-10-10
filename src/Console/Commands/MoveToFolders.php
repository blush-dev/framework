<?php

/**
 * `content:folders` command.
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
use Blush\Content\EntryFolders;
use Blush\Core\Framework;

/**
 * Lists collections' and profiles' files that aren't in the folders
 * their type keeps them in (folder entries, files in other folders, and
 * files in a pattern's folder for another date), and moves them there
 * with `--write` (D-514, D-629). It fails while any are left.
 */
#[Command('content:folders', 'List collection entries not in the folders their type keeps them in, and move them there.')]
final readonly class MoveToFolders
{
	public function __construct(private EntryFolders $folders)
	{}

	public function __invoke(
		Output $output,
		#[Option('Move the files listed.')] bool $write = false
	): ExitCode {
		$failed = false;
		$steps  = $output->steps();

		if ($write) {
			$moved = $this->folders->move(progress: $steps(...));

			$steps->finish();

			$output->status([
				...array_map(static fn (string $from, string $to): StatusLine => new StatusLine(Outcome::Pass, 'moved', "{$from} → {$to}"), array_keys($moved->renamed), array_values($moved->renamed)),
				...array_map(static fn (string $path, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$path}: {$message}"), array_keys($moved->failed), array_values($moved->failed))
			]);

			$failed = $moved->failed !== [];
		}

		$left = $this->folders->report($steps(...));

		$steps->finish();

		$output->status(array_map(static fn (string $path, string $to): StatusLine => new StatusLine(Outcome::Warning, 'move', "{$path} → {$to}"), array_keys($left), array_values($left)));

		if ($left === []) {
			$output->success('Every collection\'s entries are in the folders it keeps them in.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(
			count($left) === 1 ? '1 entry isn\'t in its type\'s folders.' : sprintf('%s entries aren\'t in their types\' folders.', number_format(count($left))),
			'Move ' . (count($left) === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:folders --write`.'
		);

		return ExitCode::Failure;
	}
}
