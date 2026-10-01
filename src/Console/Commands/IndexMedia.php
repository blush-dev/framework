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
use Blush\Console\Output;
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
		$bar    = $output->progress();
		$report = $this->indexer->index($full, static function (int $done, int $total) use ($bar): void {
			$bar->update($done, $total);
		});

		$bar->finish();

		foreach (['Added' => $report->added, 'Changed' => $report->changed, 'Removed' => $report->removed] as $label => $keys) {
			foreach ($keys as $key) {
				$output->line(sprintf('%s %s', $label, $key), Verbosity::Verbose);
			}
		}

		foreach ($report->orphans as $key) {
			$output->warning(sprintf('user/data/media/%s describes a media file that isn\'t there; move it with its file, or delete it.', $key));
		}

		$output->success(sprintf(
			'Indexed %d media %s (%d added, %d changed, %d removed) in %d ms.',
			$report->total,
			$report->total === 1 ? 'file' : 'files',
			count($report->added),
			count($report->changed),
			count($report->removed),
			intdiv(hrtime(true) - $start, 1_000_000)
		));

		if (! $report->written) {
			$output->comment('The index was already up to date.', Verbosity::Verbose);
		}

		return ExitCode::Success;
	}
}
