<?php

/**
 * `content:filenames` command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\FileNames;
use Blush\Content\Type\ContentTypes;

/**
 * Lists the entries of each type named by another pattern than its
 * `filename` (D-511, D-514), and renames them to it with `--write`
 * (D-512). A
 * rename changes only what comes before the slug, so no address moves.
 * Older names keep working, so a list alone doesn't fail.
 */
#[Command('content:filenames', 'List entries named by another pattern than their type\'s, and rename them to it.')]
final readonly class RenameToPattern
{
	public function __construct(
		private FileNames $names,
		private ContentTypes $types
	) {}

	public function __invoke(
		Output $output,
		#[Option('Rename the files listed.')] bool $write = false,
		#[Option('Only entries of this type.')] ?string $type = null
	): ExitCode {
		if ($type !== null && ! $this->types->has($type)) {
			throw new InvalidInput(sprintf('There is no "%s" content type; the types are %s.', $type, implode(', ', array_keys($this->types->all()))));
		}

		$failed = false;

		if ($write) {
			$renamed = $this->names->rename($type);

			foreach ($renamed->renamed as $from => $to) {
				$output->line(sprintf('%s  %s → %s', $output->style('renamed', Style::Green), $from, $to));
			}

			foreach ($renamed->failed as $path => $message) {
				$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $path, $message));
			}

			$failed = $renamed->failed !== [];
		}

		$report  = $this->names->report();
		$renames = $report->renames($type);

		foreach ($renames as $rename) {
			$output->line(sprintf('%s  %s → %s', $output->style('rename ', Style::Yellow), $rename->path, $rename->to));
		}

		foreach ($type === null ? array_merge([], ...array_values($report->skipped)) : $report->skipped[$type] ?? [] as $path => $why) {
			$output->line(sprintf('%s  %s keeps its name: %s.', $output->style('skipped', Style::Dim), $path, $why));
		}

		if ($renames === []) {
			$output->success('No entries need renaming: each type with a file name pattern names its entries by it.');
		} else {
			$output->info(sprintf('%d %s named by another pattern; rename %s with --write.', count($renames), count($renames) === 1 ? 'entry is' : 'entries are', count($renames) === 1 ? 'it' : 'them'));
		}

		return $failed ? ExitCode::Failure : ExitCode::Success;
	}
}
