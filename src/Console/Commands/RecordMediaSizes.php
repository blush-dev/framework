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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Console\Verbosity;
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

		try {
			if ($write) {
				$recorded = $this->sizes->record();

				foreach ($recorded->images as $key => $sizes) {
					$output->line(sprintf('%s  %s (%s)', $output->style('recorded', Style::Green), $key, self::plural(count($sizes), 'size', 'sizes')), Verbosity::Verbose);
				}

				foreach ($recorded->failed as $key => $message) {
					$output->line(sprintf('%s  %s: %s', $output->style('failed  ', Style::Red), $key, $message));
				}

				if ($recorded->images !== []) {
					$output->line(sprintf('Recorded the sizes of %s.', self::plural(count($recorded->images), 'image', 'images')));
				}

				$failed = $recorded->failed !== [];
			}

			$report = $this->sizes->report();
		} catch (MediaException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		foreach ($report->unrecorded as $key => $sizes) {
			foreach ($sizes as $size) {
				$output->line(sprintf('%s  %s  of %s', $output->style('unrecorded', Style::Yellow), $size, $key), Verbosity::Verbose);
			}
		}

		foreach ($report->stale as $key => $listed) {
			foreach ($listed as $size) {
				$output->line(sprintf('%s  %s  in %s', $output->style('stale     ', Style::Yellow), $size, $key), Verbosity::Verbose);
			}
		}

		if ($report->isClean()) {
			$output->success('Every image lists its sizes.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(implode(' ', array_filter([
			$report->unrecorded === [] ? null : sprintf('%s of %s %s recorded.', self::plural($report->count(), 'size', 'sizes'), self::plural(count($report->unrecorded), 'image', 'images'), $report->count() === 1 ? 'isn\'t' : 'aren\'t'),
			$report->stale === [] ? null : sprintf('%s files that aren\'t %s sizes.', self::plural(count($report->stale), 'image lists', 'images list'), count($report->stale) === 1 ? 'its' : 'their'),
			'Record them with --write.'
		])));

		return ExitCode::Failure;
	}

	/**
	 * Returns a count with its noun, singular or plural.
	 */
	private static function plural(int $count, string $one, string $many): string
	{
		return sprintf('%s %s', number_format($count), $count === 1 ? $one : $many);
	}
}
