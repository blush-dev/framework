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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\EntryFolders;

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

		if ($write) {
			$moved = $this->folders->move();

			foreach ($moved->renamed as $from => $to) {
				$output->line(sprintf('%s  %s → %s', $output->style('moved  ', Style::Green), $from, $to));
			}

			foreach ($moved->failed as $path => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $path, $message));
			}

			$failed = $moved->failed !== [];
		}

		$left = $this->folders->report();

		foreach ($left as $path => $to) {
			$output->line(sprintf('%s  %s → %s', $output->style('move   ', Style::Yellow), $path, $to));
		}

		if ($left === []) {
			$output->success('Every collection\'s entries are in the folders it keeps them in.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(count($left) === 1
			? '1 entry isn\'t in its type\'s folders; move it with --write.'
			: sprintf('%d entries aren\'t in their types\' folders; move them with --write.', count($left)));

		return ExitCode::Failure;
	}
}
