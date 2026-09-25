<?php

/**
 * Content index command.
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
use Blush\Console\Verbosity;
use Blush\Content\Index\Indexer;

/**
 * Builds or refreshes the content index. By default only files whose stat
 * changed are read (see `Indexer`); `--full` parses every file again.
 * Files that can't be parsed are listed and left out, and the command
 * fails so deploy scripts notice.
 */
#[Command('content:index', 'Build or refresh the content index.')]
final readonly class IndexContent
{
	public function __construct(private Indexer $indexer)
	{}

	public function __invoke(
		Output $output,
		#[Option('Parse every file again.')] bool $full = false
	): ExitCode {
		$start  = hrtime(true);
		$bar    = $output->progress();
		$report = $this->indexer->index($full, static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		foreach ($report->failures as $path => $message) {
			$output->error(sprintf('%s: %s', $path, $message));
		}

		foreach (['Added' => $report->added, 'Changed' => $report->changed, 'Removed' => $report->removed] as $label => $ids) {
			foreach ($ids as $id) {
				$output->line(sprintf('%s %s', $label, $id), Verbosity::Verbose);
			}
		}

		$summary = sprintf(
			'Indexed %d %s (%d added, %d changed, %d removed) in %d ms.',
			$report->total,
			$report->total === 1 ? 'entry' : 'entries',
			count($report->added),
			count($report->changed),
			count($report->removed),
			intdiv(hrtime(true) - $start, 1_000_000)
		);

		if ($report->failures !== []) {
			$output->error(sprintf('%s %d file(s) could not be indexed.', $summary, count($report->failures)));

			return ExitCode::Failure;
		}

		$output->success($summary);

		if (! $report->written) {
			$output->comment('The index was already up to date.', Verbosity::Verbose);
		}

		return ExitCode::Success;
	}
}
