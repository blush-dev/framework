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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\MissingParents;

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
		if ($write) {
			$done = $this->parents->create();

			foreach ($done->created as $path) {
				$output->line(sprintf('%s  %s', $output->style('created', Style::Green), $path));
			}

			foreach ($done->failed as $parent => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $parent, $message));
			}

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$count = 0;

		foreach ($this->parents->report() as $type => $keys) {
			foreach ($keys as $key => $parent) {
				$output->line(sprintf('%s  %s/%s (%s under it)', $output->style('missing', Style::Red), $type, $key, $parent['pages'] === 1 ? '1 page' : "{$parent['pages']} pages"));
				$count++;
			}
		}

		if ($count === 0) {
			$output->success('Every page is under a parent page that exists.');

			return ExitCode::Success;
		}

		$output->error(sprintf('%d parent %s no page, so the pages under %s are at the top of their tree; write %s with --write.', $count, $count === 1 ? 'folder has' : 'folders have', $count === 1 ? 'it' : 'them', $count === 1 ? 'it' : 'them'));

		return ExitCode::Failure;
	}
}
