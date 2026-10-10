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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Content\FileNameRename;
use Blush\Content\FileNames;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Framework;

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
		$steps  = $output->steps();

		if ($write) {
			$renamed = $this->names->rename($type, progress: $steps(...));

			$steps->finish();

			$output->status([
				...array_map(static fn (string $from, string $to): StatusLine => new StatusLine(Outcome::Pass, 'renamed', "{$from} → {$to}"), array_keys($renamed->renamed), array_values($renamed->renamed)),
				...array_map(static fn (string $path, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$path}: {$message}"), array_keys($renamed->failed), array_values($renamed->failed))
			]);

			$failed = $renamed->failed !== [];
		}

		$report = $this->names->report($steps(...));

		$steps->finish();

		$renames = $report->renames($type);

		$skipped = $type === null ? array_merge([], ...array_values($report->skipped)) : $report->skipped[$type] ?? [];

		$output->status([
			...array_map(static fn (FileNameRename $rename): StatusLine => new StatusLine(Outcome::Warning, 'rename', "{$rename->path} → {$rename->to}"), $renames),
			...array_map(static fn (string $path, string $why): StatusLine => new StatusLine(Outcome::Notice, 'skipped', "{$path} keeps its name: {$why}."), array_keys($skipped), array_values($skipped))
		]);

		if ($renames === []) {
			$output->success('No entries need renaming: each type with a file name pattern names its entries by it.');
		} else {
			$output->summary(Outcome::Warning, Output::count(count($renames), 'entry is', 'entries are') . ' named by another pattern');
			$output->next('Rename ' . (count($renames) === 1 ? 'it' : 'them') . ' with `' . Framework::BINARY . ' content:filenames --write`.');
		}

		return $failed ? ExitCode::Failure : ExitCode::Success;
	}
}
