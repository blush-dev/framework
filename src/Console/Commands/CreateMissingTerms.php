<?php

/**
 * Missing terms command.
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
use Blush\Content\MissingTerms;
use Blush\Core\Framework;

/**
 * Lists the terms and profiles entries name that have no file, which
 * the site leaves out (D-584), and writes a published file for each with
 * `--write` (D-478), titled as entries first wrote it. Without it, it
 * fails when any is missing.
 */
#[Command('content:terms', 'List terms and profiles entries name with no file, and write them.')]
final readonly class CreateMissingTerms
{
	public function __construct(private MissingTerms $terms)
	{}

	public function __invoke(
		Output $output,
		#[Option('Write a file for each term and profile listed.')] bool $write = false
	): ExitCode {
		$steps = $output->steps();

		if ($write) {
			$done = $this->terms->create(progress: $steps(...));

			$steps->finish();

			$output->status([
				...array_map(static fn (string $path): StatusLine => new StatusLine(Outcome::Pass, 'created', $path), array_values($done->created)),
				...array_map(static fn (string $term, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$term}: {$message}"), array_keys($done->failed), array_values($done->failed))
			]);

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$missing = $this->terms->report($steps(...));

		$steps->finish();

		$lines = [];

		foreach ($missing as $type => $slugs) {
			foreach ($slugs as $slug => $title) {
				$lines[] = new StatusLine(Outcome::Failure, 'missing', "{$type}/{$slug}" . ($title === (string) $slug ? '' : " ({$title})"));
			}
		}

		$output->status($lines);

		$count = count($lines);

		if ($count === 0) {
			$output->success('Every term and profile entries name has a file.');

			return ExitCode::Success;
		}

		$output->error(
			sprintf('%s no file, so the site leaves %s out.', Output::count($count, 'term or profile has', 'terms and profiles have'), $count === 1 ? 'it' : 'them'),
			'Write ' . ($count === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:terms --write`.'
		);

		return ExitCode::Failure;
	}
}
