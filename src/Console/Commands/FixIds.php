<?php

/**
 * Content ids command.
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
use Blush\Content\EntryIds;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\WriteException;

/**
 * Checks that every content file has an id of its own (D-477), and fixes
 * it (D-478): `--write` gives each file missing a valid id a new one, and
 * `--keep` names the file that keeps an id other files share, which then
 * get new ones (repeat it for each shared id). Without either it only
 * lists what's wrong, and fails when anything is.
 */
#[Command('content:ids', 'Check that every content file has an id, and add missing ones.')]
final readonly class FixIds
{
	public function __construct(private EntryIds $ids)
	{}

	/**
	 * @param list<string> $keep
	 */
	public function __invoke(
		Output $output,
		#[Option('Give every file missing a valid id a new one.')] bool $write = false,
		#[Option('A file (its path in the content folder) that keeps the id it shares; the others get new ones. Repeat for more.')] array $keep = []
	): ExitCode {
		$failed = false;

		try {
			foreach ($keep as $path) {
				$failed = $this->done($output, $this->ids->keep($path)) || $failed;
			}
		} catch (WriteException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($write) {
			$failed = $this->done($output, $this->ids->assignMissing()) || $failed;
		}

		$report = $this->ids->report();

		foreach ($report->missing as $path) {
			$output->line(sprintf('%s  %s', $output->style('missing', Style::Red), $path));
		}

		foreach ($report->duplicates as $id => $paths) {
			$output->line(sprintf('%s  %s', $output->style('shared ', Style::Red), $id));

			foreach ($paths as $path) {
				$output->line("         {$path}");
			}
		}

		if ($report->isClean()) {
			$output->success('Every content file has an id of its own.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(implode(' ', array_filter([
			$report->missing === [] ? null : sprintf('%s missing a valid id; add them with --write.', self::plural(count($report->missing), 'file is', 'files are')),
			$report->duplicates === [] ? null : sprintf('%s shared; keep each on one file with --keep={path}.', self::plural(count($report->duplicates), 'id is', 'ids are'))
		])));

		return ExitCode::Failure;
	}

	/**
	 * Says what a fix did, and returns whether any file couldn't be
	 * changed.
	 */
	private function done(Output $output, AssignedIds $assigned): bool
	{
		foreach ($assigned->ids as $path => $id) {
			$output->line(sprintf('%s  %s  %s', $output->style('added  ', Style::Green), $id, $path));
		}

		foreach ($assigned->failed as $path => $message) {
			$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $path, $message));
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
