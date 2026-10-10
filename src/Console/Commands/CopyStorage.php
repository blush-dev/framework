<?php

/**
 * Copy storage command.
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
use Blush\Core\Framework;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageCopy;
use Blush\Storage\StorageException;

/**
 * Copies the site's stored data from one storage driver to another, ids
 * kept (D-644, D-662): from files into SQLite for now. The site keeps
 * the driver its config names, so the last step is naming the new one.
 */
#[Command('storage:copy', 'Copy the site\'s stored data from one storage driver to another.')]
final readonly class CopyStorage
{
	public function __construct(private StorageCopy $copy)
	{}

	public function __invoke(
		Output $output,
		#[Option('The driver to copy from.')] string $from = StorageConfig::FILESYSTEM,
		#[Option('The driver to copy to.')] string $to = StorageConfig::SQLITE,
		#[Option('Replace what the database already holds.')] bool $replace = false
	): ExitCode {
		$steps = $output->steps();

		try {
			$result = $this->copy->copy($from, $to, $replace, $steps(...));

			$steps->finish();
		} catch (StorageException $e) {
			$steps->finish();
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->status(array_map(
			static fn (string $part, int $count): StatusLine => new StatusLine(Outcome::Pass, $part, number_format($count)),
			array_keys($result['copied']),
			array_values($result['copied'])
		));

		if ($result['unidentified'] > 0) {
			$output->warning(
				Output::count($result['unidentified'], 'content file', 'content files') . ' without an id weren\'t copied.',
				'Give them ids with `' . Framework::BINARY . ' content:ids --write`, then copy again with `--replace`.'
			);
		}

		$output->success(sprintf('Copied from "%s" to "%s". Sessions aren\'t copied, so everyone signs in again.', $from, $to));
		$output->next("To use it, set `STORAGE_DRIVER={$to}` in `.env`, or the driver in `config/storage.php`.");

		return ExitCode::Success;
	}
}
