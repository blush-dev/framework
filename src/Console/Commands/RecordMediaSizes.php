<?php

/**
 * Media sizes command.
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
use Blush\Core\Framework;
use Blush\Media\MediaException;
use Blush\Media\MediaSizes;

/**
 * Checks that every image's metadata file lists its sizes (D-488): its
 * resized copies (D-239), found by rule until they're
 * recorded. `--write` records them, and drops listed files that aren't
 * its sizes any more. Without it, it only says what isn't recorded, and
 * fails when anything is; `-v` lists each image and size.
 */
#[Command('media:sizes', 'Check that every image lists its sizes, and record them.')]
final readonly class RecordMediaSizes
{
	public function __construct(private MediaSizes $sizes)
	{}

	public function __invoke(
		Output $output,
		#[Option('Record each image\'s sizes in its metadata file.')] bool $write = false
	): ExitCode {
		$failed = false;
		$steps  = $output->steps();

		try {
			if ($write) {
				$recorded = $this->sizes->record(progress: $steps(...));

				$steps->finish();

				$output->status(array_map(
					static fn (string $key, array $sizes): StatusLine => new StatusLine(Outcome::Pass, 'recorded', sprintf('%s (%s)', $key, Output::count(count($sizes), 'size', 'sizes'))),
					array_keys($recorded->images),
					array_values($recorded->images)
				), level: Verbosity::Verbose);
				$output->status(array_map(
					static fn (string $key, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$key}: {$message}"),
					array_keys($recorded->failed),
					array_values($recorded->failed)
				));

				if ($recorded->images !== []) {
					$output->success(sprintf('Recorded the sizes of %s.', Output::count(count($recorded->images), 'image', 'images')));
				}

				$failed = $recorded->failed !== [];
			}

			$report = $this->sizes->report($steps(...));

			$steps->finish();
		} catch (MediaException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$lines = [];

		foreach ($report->unrecorded as $key => $sizes) {
			foreach ($sizes as $size) {
				$lines[] = new StatusLine(Outcome::Warning, 'unrecorded', "{$size} of {$key}");
			}
		}

		foreach ($report->stale as $key => $listed) {
			foreach ($listed as $size) {
				$lines[] = new StatusLine(Outcome::Warning, 'stale', "{$size} in {$key}");
			}
		}

		$output->status($lines, level: Verbosity::Verbose);

		if ($report->isClean()) {
			$output->success('Every image lists its sizes.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(
			implode(' ', array_filter([
				$report->unrecorded === [] ? null : sprintf('%s of %s %s recorded.', Output::count($report->count(), 'size', 'sizes'), Output::count(count($report->unrecorded), 'image', 'images'), $report->count() === 1 ? 'isn\'t' : 'aren\'t'),
				$report->stale === [] ? null : sprintf('%s files that aren\'t %s sizes.', Output::count(count($report->stale), 'image lists', 'images list'), count($report->stale) === 1 ? 'its' : 'their')
			])),
			'Record them with `' . Framework::BINARY . ' media:sizes --write`.'
		);

		return ExitCode::Failure;
	}
}
