<?php

/**
 * Create missing parents command.
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
use Blush\Content\MissingParents;
use Blush\Core\Framework;

/**
 * Lists the parent pages a tree's pages are kept under with no page of
 * their own, which leaves those pages at the top of the tree (D-656),
 * and writes each as a draft with `--write`, titled by its folder's
 * name. Without it, it fails when any is missing.
 */
#[Command('content:parents', 'List the parent pages tree pages are kept under with no page, and write them.')]
final readonly class CreateMissingParents
{
	public function __construct(private MissingParents $parents)
	{}

	public function __invoke(
		Output $output,
		#[Option('Write a draft page for each parent listed.')] bool $write = false
	): ExitCode {
		$steps = $output->steps();

		if ($write) {
			$done = $this->parents->create(progress: $steps(...));

			$steps->finish();

			$output->status([
				...array_map(static fn (string $path): StatusLine => new StatusLine(Outcome::Pass, 'created', $path), array_values($done->created)),
				...array_map(static fn (string $parent, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$parent}: {$message}"), array_keys($done->failed), array_values($done->failed))
			]);

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$missing = $this->parents->report($steps(...));
		$lines   = [];

		$steps->finish();

		foreach ($missing as $type => $keys) {
			foreach ($keys as $key => $parent) {
				$lines[] = new StatusLine(Outcome::Failure, 'missing', sprintf('%s/%s (%s under it)', $type, $key, Output::count($parent['pages'], 'page', 'pages')));
			}
		}

		$output->status($lines);

		$count = count($lines);

		if ($count === 0) {
			$output->success('Every page is under a parent page that exists.');

			return ExitCode::Success;
		}

		$output->error(
			sprintf('%s no page, so the pages under %s are at the top of their tree.', Output::count($count, 'parent folder has', 'parent folders have'), $count === 1 ? 'it' : 'them'),
			'Write ' . ($count === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:parents --write`.'
		);

		return ExitCode::Failure;
	}
}
