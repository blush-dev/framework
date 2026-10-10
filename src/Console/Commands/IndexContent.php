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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Console\Verbosity;
use Blush\Content\Index\Indexer;
use Blush\Content\Source\ContentFiles;

/**
 * Builds or refreshes the content index. By default only files whose stat
 * changed are read (see `Indexer`); `--full` parses every file again.
 * Files that can't be parsed are listed and left out, and the command
 * fails so deploy scripts notice.
 */
#[Command('content:index', 'Build or refresh the content index.')]
final readonly class IndexContent
{
	public function __construct(
		private Indexer $indexer,
		private ContentFiles $files
	) {}

	public function __invoke(
		Output $output,
		#[Option('Parse every file again.')] bool $full = false
	): ExitCode {
		if (! $this->files->kept()) {
			$output->success('Nothing to index: the site keeps its content in a database, which is its own index.');

			return ExitCode::Success;
		}

		$start  = hrtime(true);
		$bar    = $output->progress(label: 'Indexing content');
		$report = $this->indexer->index($full, static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		foreach ($report->failures as $path => $message) {
			$output->error(sprintf('%s: %s', $path, $message));
		}

		$output->status([
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Pass, 'added', $item), $report->added),
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Pass, 'changed', $item), $report->changed),
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Notice, 'removed', $item), $report->removed)
		], level: Verbosity::Verbose);

		$output->summary(
			$report->failures === [] ? Outcome::Pass : Outcome::Failure,
			'Indexed ' . Output::count($report->total, 'entry', 'entries'),
			Output::count(count($report->added), 'added', 'added'),
			Output::count(count($report->changed), 'changed', 'changed'),
			Output::count(count($report->removed), 'removed', 'removed'),
			...[
				...($report->failures === [] ? [] : [Output::count(count($report->failures), 'file', 'files') . ' not indexed']),
				sprintf('%d ms', intdiv(hrtime(true) - $start, 1_000_000))
			]
		);

		if ($report->failures !== []) {
			return ExitCode::Failure;
		}

		if (! $report->written) {
			$output->comment('The index was already up to date.', Verbosity::Verbose);
		}

		return ExitCode::Success;
	}
}
