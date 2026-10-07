<?php

/**
 * File refs command.
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
use Blush\Content\EntryRefs;

/**
 * Lists the content files whose links between entries aren't filed in
 * both forms (D-589): a file without the ids `refs` keeps, one naming an
 * id where Blush writes a slug, or one naming a target by a slug it no
 * longer has. `--write` files them (D-596), as Blush does whenever it
 * writes a file. Without it, it fails when any is listed.
 */
#[Command('content:refs', 'Check that links between entries are filed with their ids, and file them.')]
final readonly class FileRefs
{
	public function __construct(private EntryRefs $refs)
	{}

	public function __invoke(
		Output $output,
		#[Option('File the links of each file listed.')] bool $write = false
	): ExitCode {
		if ($write) {
			$done = $this->refs->file();

			foreach ($done->paths as $path) {
				$output->line(sprintf('%s  %s', $output->style('filed  ', Style::Green), $path));
			}

			foreach ($done->failed as $path => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $path, $message));
			}

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$stale = $this->refs->report();

		foreach ($stale as $path => $relations) {
			$output->line(sprintf('%s  %s (%s)', $output->style('unfiled', Style::Red), $path, implode(', ', $relations)));
		}

		$count = count($stale);

		if ($count === 0) {
			$output->success('Every link between entries is filed with its id.');

			return ExitCode::Success;
		}

		$output->error(sprintf('%d %s links not filed with their ids; file them with --write.', $count, $count === 1 ? 'file has' : 'files have'));

		return ExitCode::Failure;
	}
}
