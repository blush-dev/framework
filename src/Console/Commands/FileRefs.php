<?php

/**
 * File refs command.
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
use Blush\Content\EntryRefs;
use Blush\Core\Framework;

/**
 * Lists the content files whose links between entries aren't filed in
 * both forms (D-589): a file without the ids `refs` keeps, one naming an
 * id where Blush writes a slug, or one naming a target by a slug it no
 * longer has. `--write` files them (D-596), as Blush does whenever it
 * writes a file. Without it, it fails when any is listed.
 */
#[Command('content:refs', 'Check that links between entries are filed with their ids, and file them.')]
final readonly class FileRefs
{
	public function __construct(private EntryRefs $refs)
	{}

	public function __invoke(
		Output $output,
		#[Option('File the links of each file listed.')] bool $write = false
	): ExitCode {
		$steps = $output->steps();

		if ($write) {
			$done = $this->refs->file(progress: $steps(...));

			$steps->finish();

			$output->status([
				...array_map(static fn (string $path): StatusLine => new StatusLine(Outcome::Pass, 'filed', $path), $done->paths),
				...array_map(static fn (string $path, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$path}: {$message}"), array_keys($done->failed), array_values($done->failed))
			]);

			if ($done->failed !== []) {
				return ExitCode::Failure;
			}
		}

		$stale = $this->refs->report($steps(...));

		$steps->finish();

		$output->status(array_map(static fn (string $path, array $relations): StatusLine => new StatusLine(Outcome::Failure, 'unfiled', sprintf('%s (%s)', $path, implode(', ', $relations))), array_keys($stale), array_values($stale)));

		$count = count($stale);

		if ($count === 0) {
			$output->success('Every link between entries is filed with its id.');

			return ExitCode::Success;
		}

		$output->error(Output::count($count, 'file has', 'files have') . ' links not filed with their ids.', 'File them with `' . Framework::BINARY . ' content:refs --write`.');

		return ExitCode::Failure;
	}
}
