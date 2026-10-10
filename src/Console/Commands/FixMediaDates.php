<?php

/**
 * Media dates command.
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
use Blush\Media\MediaDates;
use Blush\Media\MediaException;
use Blush\Media\MediaMetadata;

/**
 * Checks that every media file says when it was added to the library
 * (D-709), the date the library is ordered by: `--write` gives each file
 * without one its likely date (`MediaDates`: its dated folder, then its
 * modified time or the date it says it was made), in its metadata file
 * under `user/data/media`, and writes a date with another offset in UTC
 * (D-711), so it sorts in the media table. Without it, it only says how
 * many need writing, and fails when any do. A site can have thousands of files, so each one
 * is listed with `-v`, with the date it has or would be given.
 */
#[Command('media:dates', 'Check that every media file has an upload date in UTC, and write missing ones.')]
final readonly class FixMediaDates
{
	public function __construct(private MediaDates $dates)
	{}

	public function __invoke(
		Output $output,
		#[Option('Give every media file without an upload date its likely one, and write the others in UTC.')] bool $write = false
	): ExitCode {
		$steps = $output->steps();

		try {
			if ($write) {
				$filled = $this->dates->fill(progress: $steps(...));

				$steps->finish();

				foreach ($filled->dates as $key => $date) {
					$output->line(sprintf('%s  %s  %s', $output->style('added  ', Style::Green), $date, $key), Verbosity::Verbose);
				}

				if ($filled->dates !== []) {
					$output->line(sprintf('Gave %s an upload date.', self::plural(count($filled->dates), 'media file', 'media files')));
				}

				foreach ($filled->failed as $key => $message) {
					$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $key, $message));
				}

				if ($filled->failed !== []) {
					$output->error(sprintf('%s couldn\'t be given an upload date.', self::plural(count($filled->failed), 'file', 'files')));

					return ExitCode::Failure;
				}
			}

			$missing = $this->dates->missing($steps(...));

			$steps->finish();
		} catch (MediaException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		// A site can have thousands: the count says it, and -v lists them.
		foreach ($missing as $key => $date) {
			$output->line(sprintf('%s  %s  %s', $output->style('missing', Style::Red), MediaMetadata::utc($date), $key), Verbosity::Verbose);
		}

		if ($missing === []) {
			$output->success('Every media file has an upload date in UTC.');

			return ExitCode::Success;
		}

		$output->error(sprintf('%s no upload date, or one not in UTC; write them with --write.', self::plural(count($missing), 'file has', 'files have')));

		return ExitCode::Failure;
	}

	/**
	 * Returns a count with its noun and verb, singular or plural.
	 */
	private static function plural(int $count, string $one, string $many): string
	{
		return sprintf('%d %s', $count, $count === 1 ? $one : $many);
	}
}
