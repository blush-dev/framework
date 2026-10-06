<?php

/**
 * `content:flatten` command.
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
use Blush\Content\FlatEntries;

/**
 * Lists collections' files that aren't directly in their collection's
 * folder (folder entries, and entries in folders below), and moves them
 * there with `--write` (D-514). It fails while any are left, since
 * `content:lint` reports them as errors.
 */
#[Command('content:flatten', 'List collection entries kept in folders, and move them into their collection\'s folder.')]
final readonly class FlattenCollections
{
	public function __construct(private FlatEntries $flat)
	{}

	public function __invoke(
		Output $output,
		#[Option('Move the files listed.')] bool $write = false
	): ExitCode {
		$failed = false;

		if ($write) {
			$moved = $this->flat->flatten();

			foreach ($moved->renamed as $from => $to) {
				$output->line(sprintf('%s  %s → %s', $output->style('moved  ', Style::Green), $from, $to));
			}

			foreach ($moved->failed as $path => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $path, $message));
			}

			$failed = $moved->failed !== [];
		}

		$left = $this->flat->report();

		foreach ($left as $path => $to) {
			$output->line(sprintf('%s  %s → %s', $output->style('move   ', Style::Yellow), $path, $to));
		}

		if ($left === []) {
			$output->success('Every collection\'s entries are files in its folder.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(sprintf('%d %s kept in a folder; move %s with --write.', count($left), count($left) === 1 ? 'entry is' : 'entries are', count($left) === 1 ? 'it' : 'them'));

		return ExitCode::Failure;
	}
}
