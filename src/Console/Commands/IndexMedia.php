<?php

/**
 * Index media command.
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
use Blush\Media\Index\MediaIndexer;

/**
 * Builds or refreshes the media index (D-288). By default only files
 * whose size or modified time, or whose metadata file, changed are read
 * (see `MediaIndexer`); `--full` reads every file again. Metadata files
 * whose media file is gone are listed as warnings.
 */
#[Command('media:index', 'Build or refresh the media index.')]
final readonly class IndexMedia
{
	public function __construct(private MediaIndexer $indexer)
	{}

	public function __invoke(
		Output $output,
		#[Option('Read every file again.')] bool $full = false
	): ExitCode {
		$start  = hrtime(true);
		$bar    = $output->progress(label: 'Indexing media');
		$report = $this->indexer->index($full, static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		$output->status([
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Pass, 'added', $item), $report->added),
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Pass, 'changed', $item), $report->changed),
			...array_map(static fn (string $item): StatusLine => new StatusLine(Outcome::Notice, 'removed', $item), $report->removed)
		], level: Verbosity::Verbose);

		foreach ($report->orphans as $key) {
			$output->warning(sprintf('user/data/media/%s describes a media file that isn\'t there.', $key), 'Move it with its file, or delete it.');
		}

		$output->summary(
			Outcome::Pass,
			'Indexed ' . Output::count($report->total, 'media file', 'media files'),
			Output::count(count($report->added), 'added', 'added'),
			Output::count(count($report->changed), 'changed', 'changed'),
			Output::count(count($report->removed), 'removed', 'removed'),
			sprintf('%d ms', intdiv(hrtime(true) - $start, 1_000_000))
		);

		if (! $report->written) {
			$output->comment('The index was already up to date.', Verbosity::Verbose);
		}

		return ExitCode::Success;
	}
}
