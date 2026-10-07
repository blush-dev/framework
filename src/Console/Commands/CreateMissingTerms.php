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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\MissingTerms;

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
		if ($write) {
			$done = $this->terms->create();

			foreach ($done->created as $path) {
				$output->line(sprintf('%s  %s', $output->style('created', Style::Green), $path));
			}

			foreach ($done->failed as $term => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $term, $message));
			}

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$missing = $this->terms->report();
		$count   = 0;

		foreach ($missing as $type => $slugs) {
			foreach ($slugs as $slug => $title) {
				$output->line(sprintf('%s  %s/%s%s', $output->style('missing', Style::Red), $type, $slug, $title === $slug ? '' : " ({$title})"));
				$count++;
			}
		}

		if ($count === 0) {
			$output->success('Every term and profile entries name has a file.');

			return ExitCode::Success;
		}

		$output->error(sprintf('%d %s no file, so the site leaves %s out; write %s with --write.', $count, $count === 1 ? 'term or profile has' : 'terms and profiles have', $count === 1 ? 'it' : 'them', $count === 1 ? 'it' : 'them'));

		return ExitCode::Failure;
	}
}
