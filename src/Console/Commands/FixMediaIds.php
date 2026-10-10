<?php

/**
 * Media ids command.
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
use Blush\Media\AssignedMediaIds;
use Blush\Media\MediaException;
use Blush\Media\MediaIds;

/**
 * Checks that every media file has an id of its own (D-487), as
 * `content:ids` does for content: `--write` gives each file missing a
 * valid id a new one, in its metadata file under `user/data/media`, and
 * `--keep` names the file that keeps an id other files share, which then
 * get new ones (repeat it for each shared id). Sizes of another image
 * (D-239) have no id of their own and aren't listed. Without either it
 * only says what's wrong, and fails when anything is. A site can have
 * thousands of files, so each one missing an id or given one is listed
 * with `-v`.
 */
#[Command('media:ids', 'Check that every media file has an id, and add missing ones.')]
final readonly class FixMediaIds
{
	public function __construct(private MediaIds $ids)
	{}

	/**
	 * @param list<string> $keep
	 */
	public function __invoke(
		Output $output,
		#[Option('Give every media file missing a valid id a new one.')] bool $write = false,
		#[Option('A media file (its path in the media folder) that keeps the id it shares; the others get new ones. Repeat for more.')] array $keep = []
	): ExitCode {
		$failed = false;
		$steps  = $output->steps();

		try {
			foreach ($keep as $key) {
				$kept = $this->ids->keep($key, progress: $steps(...));

				$steps->finish();

				$failed = $this->done($output, $kept) || $failed;
			}

			if ($write) {
				$assigned = $this->ids->assignMissing(progress: $steps(...));

				$steps->finish();

				$failed = $this->done($output, $assigned) || $failed;
			}

			$report = $this->ids->report($steps(...));

			$steps->finish();
		} catch (MediaException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		// A site can have thousands: the count says it, and -v lists them.
		$output->status(array_map(static fn (string $path): StatusLine => new StatusLine(Outcome::Failure, 'missing', $path), $report->missing), level: Verbosity::Verbose);
		$output->status(array_map(
			static fn (string $id, array $paths): StatusLine => new StatusLine(Outcome::Failure, 'shared', $id, implode("\n", $paths)),
			array_keys($report->duplicates),
			array_values($report->duplicates)
		));

		if ($report->isClean()) {
			$output->success('Every media file has an id of its own.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(
			implode(' ', array_filter([
				$report->missing === [] ? null : Output::count(count($report->missing), 'file is', 'files are') . ' missing a valid id.',
				$report->duplicates === [] ? null : Output::count(count($report->duplicates), 'id is', 'ids are') . ' shared.'
			])),
			implode(' ', array_filter([
				$report->missing === [] ? null : 'Add the missing ones with `' . Framework::BINARY . ' media:ids --write`.',
				$report->duplicates === [] ? null : 'Keep each shared id on one file with `' . Framework::BINARY . ' media:ids --keep={path}`.'
			]))
		);

		return ExitCode::Failure;
	}

	/**
	 * Says what a fix did (each file with -v), and returns whether any
	 * file couldn't be changed.
	 */
	private function done(Output $output, AssignedMediaIds $assigned): bool
	{
		$output->status(array_map(
			static fn (string $path, string $id): StatusLine => new StatusLine(Outcome::Pass, 'added', "{$path} ({$id})"),
			array_keys($assigned->ids),
			array_values($assigned->ids)
		), level: Verbosity::Verbose);
		$output->status(array_map(
			static fn (string $path, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$path}: {$message}"),
			array_keys($assigned->failed),
			array_values($assigned->failed)
		));

		if ($assigned->ids !== []) {
			$output->success(sprintf('Gave %s a new id.', Output::count(count($assigned->ids), 'media file', 'media files')));
		}

		return $assigned->failed !== [];
	}
}
