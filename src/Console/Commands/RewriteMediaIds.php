<?php

/**
 * Rewrite media fields to ids command.
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
use Blush\Content\MediaFieldIds;
use Blush\Core\Framework;
use Blush\Media\MediaException;

/**
 * Lists the entries whose media fields are still written as paths
 * (D-773), and with `--write` rewrites each to its file's id. A path that
 * names no file in the library, or one without an id yet, is left and
 * listed. Without `--write`, it fails when any is listed.
 */
#[Command('content:media-ids', 'Check that media fields name their files by id, and rewrite paths to ids.')]
final readonly class RewriteMediaIds
{
	public function __construct(private MediaFieldIds $ids)
	{}

	/**
	 * @throws MediaException
	 */
	public function __invoke(
		Output $output,
		#[Option('Rewrite each path that names a file to its id.')] bool $write = false
	): ExitCode {
		if ($write) {
			$done = $this->ids->write();

			$output->status([
				...array_map(static fn (string $id): StatusLine => new StatusLine(Outcome::Pass, 'rewritten', $id), $done['changed']),
				...array_map(static fn (string $id, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$id}: {$message}"), array_keys($done['failed']), array_values($done['failed']))
			]);

			if ($done['failed'] !== []) {
				return ExitCode::Failure;
			}
		}

		$lines = [];

		foreach ($this->ids->report() as $found) {
			foreach ($found['values'] as $value) {
				$lines[] = new StatusLine(
					$value['id'] === null ? Outcome::Failure : Outcome::Warning,
					$value['id'] === null ? 'no file' : 'a path',
					sprintf('%s (%s), %s: %s%s', $found['title'], $found['type'], $value['field'], $value['path'], $value['id'] === null ? ', which names no file with an id' : '')
				);
			}
		}

		$output->status($lines);

		if ($lines === []) {
			$output->success('Every media field names its file by id.');

			return ExitCode::Success;
		}

		$output->error(
			Output::count(count($lines), 'media value is', 'media values are') . ' written as a path.',
			'Rewrite them with `' . Framework::BINARY . ' content:media-ids --write`; give files ids first with `' . Framework::BINARY . ' media:ids`.'
		);

		return ExitCode::Failure;
	}
}
