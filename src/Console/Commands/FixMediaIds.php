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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Console\Verbosity;
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

		try {
			foreach ($keep as $key) {
				$failed = $this->done($output, $this->ids->keep($key)) || $failed;
			}

			if ($write) {
				$failed = $this->done($output, $this->ids->assignMissing()) || $failed;
			}

			$report = $this->ids->report();
		} catch (MediaException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		// A site can have thousands: the count says it, and -v lists them.
		foreach ($report->missing as $key) {
			$output->line(sprintf('%s  %s', $output->style('missing', Style::Red), $key), Verbosity::Verbose);
		}

		foreach ($report->duplicates as $id => $keys) {
			$output->line(sprintf('%s  %s', $output->style('shared ', Style::Red), $id));

			foreach ($keys as $key) {
				$output->line("         {$key}");
			}
		}

		if ($report->isClean()) {
			$output->success('Every media file has an id of its own.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(implode(' ', array_filter([
			$report->missing === [] ? null : sprintf('%s missing a valid id; add them with --write.', self::plural(count($report->missing), 'file is', 'files are')),
			$report->duplicates === [] ? null : sprintf('%s shared; keep each on one file with --keep={path}.', self::plural(count($report->duplicates), 'id is', 'ids are'))
		])));

		return ExitCode::Failure;
	}

	/**
	 * Says what a fix did (each file with -v), and returns whether any
	 * file couldn't be changed.
	 */
	private function done(Output $output, AssignedMediaIds $assigned): bool
	{
		foreach ($assigned->ids as $key => $id) {
			$output->line(sprintf('%s  %s  %s', $output->style('added  ', Style::Green), $id, $key), Verbosity::Verbose);
		}

		if ($assigned->ids !== []) {
			$output->line(sprintf('Gave %s a new id.', self::plural(count($assigned->ids), 'media file', 'media files')));
		}

		foreach ($assigned->failed as $key => $message) {
			$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $key, $message));
		}

		return $assigned->failed !== [];
	}

	/**
	 * Returns a count with its noun and verb, singular or plural.
	 */
	private static function plural(int $count, string $one, string $many): string
	{
		return sprintf('%d %s', $count, $count === 1 ? $one : $many);
	}
}
