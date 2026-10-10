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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Content\EntryIds;
use Blush\Content\Writer\AssignedIds;
use Blush\Content\Writer\WriteException;
use Blush\Core\Framework;

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
		$steps  = $output->steps();

		try {
			foreach ($keep as $path) {
				$kept = $this->ids->keep($path, progress: $steps(...));

				$steps->finish();

				$failed = $this->done($output, $kept) || $failed;
			}
		} catch (WriteException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($write) {
			$assigned = $this->ids->assignMissing(progress: $steps(...));

			$steps->finish();

			$failed = $this->done($output, $assigned) || $failed;
		}

		$report = $this->ids->report($steps(...));

		$steps->finish();

		$output->status([
			...array_map(static fn (string $path): StatusLine => new StatusLine(Outcome::Failure, 'missing', $path), $report->missing),
			...array_map(
				static fn (string $id, array $paths): StatusLine => new StatusLine(Outcome::Failure, 'shared', $id, implode("\n", $paths)),
				array_keys($report->duplicates),
				array_values($report->duplicates)
			)
		]);

		if ($report->isClean()) {
			$output->success('Every content file has an id of its own.');

			return $failed ? ExitCode::Failure : ExitCode::Success;
		}

		$output->error(
			implode(' ', array_filter([
				$report->missing === [] ? null : Output::count(count($report->missing), 'file is', 'files are') . ' missing a valid id.',
				$report->duplicates === [] ? null : Output::count(count($report->duplicates), 'id is', 'ids are') . ' shared.'
			])),
			implode(' ', array_filter([
				$report->missing === [] ? null : 'Add the missing ones with `' . Framework::BINARY . ' content:ids --write`.',
				$report->duplicates === [] ? null : 'Keep each shared id on one file with `' . Framework::BINARY . ' content:ids --keep={path}`.'
			]))
		);

		return ExitCode::Failure;
	}

	/**
	 * Says what a fix did, and returns whether any
	 * file couldn't be changed.
	 */
	private function done(Output $output, AssignedIds $assigned): bool
	{
		$output->status(array_map(
			static fn (string $path, string $id): StatusLine => new StatusLine(Outcome::Pass, 'added', "{$path} ({$id})"),
			array_keys($assigned->ids),
			array_values($assigned->ids)
		));
		$output->status(array_map(
			static fn (string $path, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$path}: {$message}"),
			array_keys($assigned->failed),
			array_values($assigned->failed)
		));

		if ($assigned->ids !== []) {
			$output->success(sprintf('Gave %s a new id.', Output::count(count($assigned->ids), 'file', 'files')));
		}

		return $assigned->failed !== [];
	}
}
