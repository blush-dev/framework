<?php

/**
 * Publish command.
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
use Blush\Console\Verbosity;
use Blush\Publish\Publisher;
use Blush\Publish\PublishInProgress;

/**
 * Puts the site's current files live, as the webhook does (D-013): an
 * optional `git pull` in `user/`, the compiled caches that depend on
 * site data, an incremental reindex, and a cleared cache store with a
 * new content version. `--pull` and `--no-pull` override
 * `PublishConfig::$git`. It fails when the pull does or a file can't be
 * indexed, so deploy scripts notice.
 */
#[Command('publish', 'Pull content, reindex, and clear the caches.')]
final readonly class Publish
{
	public function __construct(private Publisher $publisher)
	{}

	public function __invoke(
		Output $output,
		#[Option('Run git pull in user/ first.')] bool $pull = false,
		#[Option('Skip git pull, even when config turns it on.')] bool $noPull = false
	): ExitCode {
		if ($pull && $noPull) {
			$output->error('Use --pull or --no-pull, not both.');

			return ExitCode::Invalid;
		}

		$steps = $output->steps();

		try {
			$report = $this->publisher->publish($pull ? true : ($noPull ? false : null), progress: $steps(...));
		} catch (PublishInProgress $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		} finally {
			$steps->finish();
		}

		if ($report->pull !== null) {
			foreach (explode("\n", $report->pull->output) as $line) {
				if ($line !== '') {
					$output->line($line, $report->pull->successful ? Verbosity::Verbose : Verbosity::Normal);
				}
			}
		}

		if (! $report->isPublished()) {
			$output->error('git pull failed, so nothing was published.');

			return ExitCode::Failure;
		}

		$index = $report->index;

		foreach ($index->failures ?? [] as $path => $message) {
			$output->error(sprintf('%s: %s', $path, $message));
		}

		$output->definitions(array_filter([
			'Entries' => $output->join(Output::count($index->total ?? 0, 'entry', 'entries'), count($index->added ?? []) . ' added', count($index->changed ?? []) . ' changed', count($index->removed ?? []) . ' removed'),
			'Media'   => $report->media === null ? '' : $output->join(Output::count($report->media->total, 'file', 'files'), count($report->media->added) . ' added', count($report->media->changed) . ' changed', count($report->media->removed) . ' removed'),
			'Routes'  => $report->routes ? 'recompiled' : '',
			'Types'   => $report->types ? 'recompiled' : ''
		], static fn (string $value): bool => $value !== ''), level: Verbosity::Verbose);

		$output->summary(
			$report->isSuccessful() ? Outcome::Pass : Outcome::Failure,
			sprintf('Published in %d ms', $report->milliseconds),
			"content version {$report->version}",
			...($report->isSuccessful() ? [] : [Output::count(count($index->failures ?? []), 'file', 'files') . ' not indexed'])
		);

		if (! $report->isSuccessful()) {
			return ExitCode::Failure;
		}

		return ExitCode::Success;
	}
}
