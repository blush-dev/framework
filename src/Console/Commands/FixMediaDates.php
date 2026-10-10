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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Console\Verbosity;
use Blush\Core\Framework;
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

				$output->status(array_map(
					static fn (string $key, string $date): StatusLine => new StatusLine(Outcome::Pass, 'added', "{$key} ({$date})"),
					array_keys($filled->dates),
					array_values($filled->dates)
				), level: Verbosity::Verbose);
				$output->status(array_map(
					static fn (string $key, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$key}: {$message}"),
					array_keys($filled->failed),
					array_values($filled->failed)
				));

				if ($filled->dates !== []) {
					$output->success(sprintf('Gave %s an upload date.', Output::count(count($filled->dates), 'media file', 'media files')));
				}

				if ($filled->failed !== []) {
					$output->error(sprintf('%s couldn\'t be given an upload date.', Output::count(count($filled->failed), 'file', 'files')));

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
		$output->status(array_map(
			static fn (string $key, mixed $date): StatusLine => new StatusLine(Outcome::Failure, 'missing', sprintf('%s (%s)', $key, MediaMetadata::utc($date))),
			array_keys($missing),
			array_values($missing)
		), level: Verbosity::Verbose);

		if ($missing === []) {
			$output->success('Every media file has an upload date in UTC.');

			return ExitCode::Success;
		}

		$output->error(
			sprintf('%s no upload date, or one not in UTC.', Output::count(count($missing), 'file has', 'files have')),
			'Write them with `' . Framework::BINARY . ' media:dates --write`.'
		);

		return ExitCode::Failure;
	}
}
